<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Support\SupervisorGroupAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeGroupTest extends TestCase
{
    public function test_superadmin_can_assign_a_supervisor_and_share_members_across_groups(): void
    {
        $this->createGroupSchema();
        $superadmin = $this->createUser('superadmin', 'root');
        $supervisorOne = $this->createEmployeeWithAccount(1, 'EMP-1', 'Supervisor One');
        $supervisorTwo = $this->createEmployeeWithAccount(2, 'EMP-2', 'Supervisor Two');
        $member = $this->createEmployeeWithAccount(3, 'EMP-3', 'Group Member');

        $this->actingAs($superadmin)
            ->post(route('employee-groups.store'), [
                'name' => 'Operations',
                'supervisor_employee_id' => $supervisorOne->id,
                'members' => [$member->id],
            ])
            ->assertRedirect(route('employee-groups.index'));

        $firstGroupId = DB::table('employee_groups')->where('name', 'Operations')->value('id');

        $this->post(route('employee-groups.store'), [
            'name' => 'Support',
            'supervisor_employee_id' => $supervisorTwo->id,
            'members' => [$member->id],
        ])->assertRedirect(route('employee-groups.index'));

        $this->post(route('employee-groups.store'), [
            'name' => 'Finance',
            'supervisor_employee_id' => $supervisorOne->id,
            'members' => [],
        ])->assertSessionHasErrors('supervisor_employee_id');

        $this->assertSame(
            2,
            DB::table('employee_group_members')->where('employee_id', $member->id)->count()
        );
        $this->assertSame(
            Role::where('slug', 'supervisor')->value('id'),
            $supervisorOne->account->fresh()->role_id
        );
        $this->assertSame(
            ['EMP-3'],
            SupervisorGroupAccess::memberUserIds($supervisorOne->account->fresh())
        );
        $this->assertNotNull($firstGroupId);
    }

    public function test_changing_a_groups_supervisor_updates_both_account_roles(): void
    {
        $this->createGroupSchema();
        $superadmin = $this->createUser('superadmin', 'root');
        $previousSupervisor = $this->createEmployeeWithAccount(1, 'EMP-1', 'Supervisor One');
        $newSupervisor = $this->createEmployeeWithAccount(2, 'EMP-2', 'Supervisor Two');

        $this->actingAs($superadmin)->post(route('employee-groups.store'), [
            'name' => 'Operations',
            'supervisor_employee_id' => $previousSupervisor->id,
            'members' => [],
        ])->assertRedirect(route('employee-groups.index'));

        $groupId = DB::table('employee_groups')->value('id');
        $this->get(route('employee-groups.edit', $groupId))->assertOk();

        $this->put(route('employee-groups.update', $groupId), [
            'name' => 'Operations',
            'supervisor_employee_id' => $newSupervisor->id,
            'members' => [],
        ])->assertRedirect(route('employee-groups.index'));

        $this->assertSame(
            'employee',
            $previousSupervisor->account->fresh()->role->slug
        );
        $this->assertSame(
            'supervisor',
            $newSupervisor->account->fresh()->role->slug
        );
    }

    public function test_employees_without_login_accounts_are_available_as_group_members(): void
    {
        $this->createGroupSchema();
        $superadmin = $this->createUser('superadmin', 'root');
        $supervisor = $this->createEmployeeWithAccount(1, 'EMP-1', 'Supervisor');
        $member = Employee::create([
            'id' => 2,
            'user_id' => 'EMP-2',
            'employee_name' => 'Employee Without Login',
        ]);

        $this->actingAs($superadmin)
            ->get(route('employee-groups.create'))
            ->assertOk()
            ->assertSee('Employee Without Login');

        $this->post(route('employee-groups.store'), [
            'name' => 'Operations',
            'supervisor_employee_id' => $supervisor->id,
            'members' => [$member->id],
        ])->assertRedirect(route('employee-groups.index'));

        $this->assertDatabaseHas('employee_group_members', [
            'employee_group_id' => DB::table('employee_groups')->where('name', 'Operations')->value('id'),
            'employee_id' => $member->id,
        ]);
    }

    public function test_supervisor_can_approve_only_leave_requests_for_group_members(): void
    {
        $this->createGroupSchema();
        $supervisor = $this->createEmployeeWithAccount(1, 'EMP-1', 'Supervisor', 'supervisor');
        $member = $this->createEmployeeWithAccount(2, 'EMP-2', 'Member');
        $other = $this->createEmployeeWithAccount(3, 'EMP-3', 'Other');
        $groupId = DB::table('employee_groups')->insertGetId([
            'name' => 'Operations',
            'supervisor_employee_id' => $supervisor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('employee_group_members')->insert([
            'employee_group_id' => $groupId,
            'employee_id' => $member->id,
        ]);
        $memberLeaveId = DB::table('leaves')->insertGetId([
            'user_id' => $member->user_id,
            'leave_date' => '2026-11-02',
            'leave_type' => 'casual',
            'approval_status' => 0,
        ]);
        $otherLeaveId = DB::table('leaves')->insertGetId([
            'user_id' => $other->user_id,
            'leave_date' => '2026-11-03',
            'leave_type' => 'sick',
            'approval_status' => 0,
        ]);

        $this->actingAs($supervisor->account)
            ->post(route('leaves.approve', $memberLeaveId))
            ->assertRedirect();
        $this->post(route('leaves.approve', $otherLeaveId))->assertForbidden();
        $this->get(route('employee.attendance', $other))->assertForbidden();

        $this->assertDatabaseHas('leaves', ['leave_id' => $memberLeaveId, 'approval_status' => 1]);
        $this->assertDatabaseHas('leaves', ['leave_id' => $otherLeaveId, 'approval_status' => 0]);
    }

    public function test_supervisor_can_open_a_team_attendance_list_scoped_to_group_members(): void
    {
        $this->createGroupSchema();
        $supervisor = $this->createEmployeeWithAccount(1, 'EMP-1', 'Supervisor', 'supervisor');
        $member = $this->createEmployeeWithAccount(2, 'EMP-2', 'Group Member');
        $other = $this->createEmployeeWithAccount(3, 'EMP-3', 'Other Employee');
        $groupId = DB::table('employee_groups')->insertGetId([
            'name' => 'Operations',
            'supervisor_employee_id' => $supervisor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('employee_group_members')->insert([
            'employee_group_id' => $groupId,
            'employee_id' => $member->id,
        ]);

        $this->actingAs($supervisor->account)
            ->get(route('employee.attendance.team'))
            ->assertOk()
            ->assertSee('Group Member')
            ->assertSee(route('employee.attendance', $member), false)
            ->assertDontSee('Other Employee');
    }

    public function test_supervisor_can_approve_only_wfh_requests_for_group_members(): void
    {
        $this->createGroupSchema();
        $supervisor = $this->createEmployeeWithAccount(1, 'EMP-1', 'Supervisor', 'supervisor');
        $member = $this->createEmployeeWithAccount(2, 'EMP-2', 'Member');
        $other = $this->createEmployeeWithAccount(3, 'EMP-3', 'Other');
        $groupId = DB::table('employee_groups')->insertGetId([
            'name' => 'Operations',
            'supervisor_employee_id' => $supervisor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('employee_group_members')->insert([
            'employee_group_id' => $groupId,
            'employee_id' => $member->id,
        ]);
        $memberRequestId = DB::table('work_from_home_requests')->insertGetId([
            'user_id' => $member->user_id,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-02',
            'reason' => 'Home repair',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherRequestId = DB::table('work_from_home_requests')->insertGetId([
            'user_id' => $other->user_id,
            'start_date' => '2026-11-03',
            'end_date' => '2026-11-03',
            'reason' => 'Appointment',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($supervisor->account)
            ->post(route('wfh-requests.approve', $memberRequestId))
            ->assertRedirect(route('wfh-requests.index'));
        $this->post(route('wfh-requests.approve', $otherRequestId))->assertForbidden();

        $this->assertDatabaseHas('work_from_home_requests', [
            'id' => $memberRequestId,
            'status' => 'approved',
            'reviewed_by' => $supervisor->account->id,
        ]);
        $this->assertDatabaseHas('work_from_home_requests', [
            'id' => $otherRequestId,
            'status' => 'pending',
        ]);
    }

    public function test_supervisor_retains_employee_leave_and_wfh_self_service(): void
    {
        $this->createGroupSchema();
        $supervisor = $this->createEmployeeWithAccount(1, 'EMP-1', 'Supervisor', 'supervisor');

        $this->actingAs($supervisor->account)
            ->get(route('employee.leaves.create'))
            ->assertOk();
        $this->get(route('employee.wfh.create'))->assertOk();
    }

    private function createGroupSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->foreignId('role_id');
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 50)->nullable();
            $table->string('employee_name');
            $table->timestamps();
        });
        Schema::create('employee_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedBigInteger('supervisor_employee_id')->unique();
            $table->timestamps();
        });
        Schema::create('employee_group_members', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_group_id');
            $table->unsignedBigInteger('employee_id');
            $table->primary(['employee_group_id', 'employee_id']);
        });
        Schema::create('leaves', function (Blueprint $table) {
            $table->id('leave_id');
            $table->string('user_id');
            $table->date('leave_date');
            $table->string('leave_type');
            $table->string('leave_description')->nullable();
            $table->integer('approval_status');
        });
        Schema::create('work_from_home_requests', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 50);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason');
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        DB::table('roles')->insert([
            ['name' => 'Administrator', 'slug' => 'admin', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Employee', 'slug' => 'employee', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Superadmin', 'slug' => 'superadmin', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Supervisor', 'slug' => 'supervisor', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function createEmployeeWithAccount(
        int $id,
        string $userId,
        string $name,
        string $roleSlug = 'employee'
    ): Employee {
        $employee = Employee::create([
            'id' => $id,
            'user_id' => $userId,
            'employee_name' => $name,
        ]);
        $this->createUser($roleSlug, strtolower(str_replace(' ', '.', $name)), $id);

        return $employee;
    }

    private function createUser(string $roleSlug, string $suffix, ?int $employeeId = null): User
    {
        return User::create([
            'name' => ucfirst($suffix),
            'email' => $suffix . '@example.test',
            'password' => 'password',
            'role_id' => Role::where('slug', $roleSlug)->value('id'),
            'employee_id' => $employeeId,
        ]);
    }
}
