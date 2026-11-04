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
        $member = $this->createEmployeeWithAccount(2, 'EMP-2', 'Group Member');

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
            'supervisor_employee_id' => $supervisorOne->id,
            'members' => [$member->id],
        ])->assertRedirect(route('employee-groups.index'));

        $this->assertSame(
            2,
            DB::table('employee_group_members')->where('employee_id', $member->id)->count()
        );
        $this->assertSame(
            2,
            DB::table('employee_groups')->where('supervisor_employee_id', $supervisorOne->id)->count()
        );
        $this->assertSame(
            Role::where('slug', 'supervisor')->value('id'),
            $supervisorOne->account->fresh()->role_id
        );
        $this->assertSame(
            ['EMP-2'],
            SupervisorGroupAccess::memberUserIds($supervisorOne->account->fresh())
        );
        $this->assertNotNull($firstGroupId);
    }

    public function test_supervisor_can_view_all_their_groups(): void
    {
        $this->createGroupSchema();
        $supervisor = $this->createEmployeeWithAccount(1, 'EMP-1', 'Supervisor', 'supervisor');
        $member = $this->createEmployeeWithAccount(2, 'EMP-2', 'Member');
        foreach (['Operations', 'Support'] as $name) {
            $groupId = DB::table('employee_groups')->insertGetId([
                'name' => $name,
                'supervisor_employee_id' => $supervisor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('employee_group_members')->insert([
                'employee_group_id' => $groupId,
                'employee_id' => $member->id,
            ]);
        }

        $this->actingAs($supervisor->account)
            ->get(route('employee.groups'))
            ->assertOk()
            ->assertSee('Operations')
            ->assertSee('Support')
            ->assertSee('Team Attendance')
            ->assertSee('Leave Approvals')
            ->assertSee('WFH Requests');
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
        Schema::create('daily_attendance_step3', function (Blueprint $table) {
            $table->string('user_id');
            $table->date('attendance_date');
            $table->string('attendance_status');
            $table->string('first_punch')->nullable();
            $table->string('last_punch')->nullable();
            $table->string('lunch_out')->nullable();
            $table->string('lunch_in')->nullable();
            $table->string('punch_1')->nullable();
            $table->string('punch_2')->nullable();
            $table->string('punch_3')->nullable();
            $table->string('punch_4')->nullable();
            $table->string('punch_5')->nullable();
        });
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
        $today = now()->toDateString();
        DB::table('daily_attendance_step3')->insert([
            [
                'user_id' => 'EMP-1',
                'attendance_date' => $today,
                'attendance_status' => 'Present',
                'first_punch' => '08:00',
                'last_punch' => '17:00',
                'lunch_out' => null,
                'lunch_in' => null,
                'punch_1' => '08:00',
                'punch_2' => '12:00',
                'punch_3' => '12:30',
                'punch_4' => '17:00',
                'punch_5' => null,
            ],
            [
                'user_id' => 'EMP-2',
                'attendance_date' => $today,
                'attendance_status' => 'Present',
                'first_punch' => '08:30',
                'last_punch' => '17:00',
                'lunch_out' => null,
                'lunch_in' => null,
                'punch_1' => '08:30',
                'punch_2' => '12:30',
                'punch_3' => '13:00',
                'punch_4' => '17:00',
                'punch_5' => null,
            ],
            [
                'user_id' => 'EMP-2',
                'attendance_date' => now()->subDay()->toDateString(),
                'attendance_status' => 'Absent',
                'first_punch' => null,
                'last_punch' => null,
                'lunch_out' => null,
                'lunch_in' => null,
                'punch_1' => null,
                'punch_2' => null,
                'punch_3' => null,
                'punch_4' => null,
                'punch_5' => null,
            ],
            [
                'user_id' => 'EMP-3',
                'attendance_date' => $today,
                'attendance_status' => 'Present',
                'first_punch' => '09:00',
                'last_punch' => '17:00',
                'lunch_out' => '13:00',
                'lunch_in' => '13:30',
                'punch_1' => '09:00',
                'punch_2' => '13:00',
                'punch_3' => '13:30',
                'punch_4' => '17:00',
                'punch_5' => null,
            ],
        ]);

        $this->actingAs($supervisor->account)
            ->get(route('employee.attendance.team'))
            ->assertOk()
            ->assertSee('Group Member')
            ->assertSee('name="date"', false)
            ->assertSee('value="'.$today.'"', false)
            ->assertSee('Attendance ('.$today.')')
            ->assertSee($today)
            ->assertSee('Present')
            ->assertSee('08:30')
            ->assertSee('Supervisor (You)')
            ->assertSee('08:00')
            ->assertSee('Lunch Out')
            ->assertSee('12:30')
            ->assertSee('Lunch In')
            ->assertSee('13:00')
            ->assertSee('Lunch Time')
            ->assertSee('30 minutes')
            ->assertSee('Check Out')
            ->assertSee('Review Punches')
            ->assertDontSee('<select', false)
            ->assertDontSee('Absent')
            ->assertDontSee('09:00')
            ->assertSee(route('employee.attendance', $member), false)
            ->assertDontSee('Other Employee');

        $content = $this->get(route('employee.attendance.team'))
            ->assertOk()
            ->getContent();
        $this->assertLessThan(
            strpos($content, 'Group Member'),
            strpos($content, 'Supervisor (You)')
        );

        $selectedDate = now()->subDay()->toDateString();
        $this->get(route('employee.attendance.team', ['date' => $selectedDate]))
            ->assertOk()
            ->assertSee('value="'.$selectedDate.'"', false)
            ->assertSee('Attendance ('.$selectedDate.')')
            ->assertSee('Absent')
            ->assertDontSee('08:30');
    }

    public function test_supervisor_can_approve_custom_punch_assignments_for_group_members(): void
    {
        $this->createGroupSchema();
        Schema::create('daily_attendance_step3', function (Blueprint $table) {
            $table->string('user_id');
            $table->date('attendance_date');
            $table->string('attendance_status');
            $table->string('first_punch')->nullable();
            $table->string('last_punch')->nullable();
            $table->string('lunch_out')->nullable();
            $table->string('lunch_in')->nullable();
            foreach (range(1, 5) as $punchNumber) {
                $table->string('punch_'.$punchNumber)->nullable();
            }
        });
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
        $today = now()->toDateString();
        DB::table('daily_attendance_step3')->insert([
            'user_id' => 'EMP-2',
            'attendance_date' => $today,
            'attendance_status' => 'Present',
            'first_punch' => '08:30',
            'last_punch' => '17:30',
            'lunch_out' => '08:35',
            'lunch_in' => '12:30',
            'punch_1' => '08:30',
            'punch_2' => '08:35',
            'punch_3' => '12:30',
            'punch_4' => '13:00',
            'punch_5' => '17:30',
        ]);

        $this->actingAs($supervisor->account);
        $this->get(route('employee.attendance.team.punch-selection.edit', [
            'employee' => $member,
            'date' => $today,
        ]))
            ->assertOk()
            ->assertSee('Review Attendance Punches')
            ->assertSee('name="lunch_out_punch"', false)
            ->assertSee('name="lunch_in_punch"', false)
            ->assertSee('Punch 2 — 08:35')
            ->assertSee('selected', false);
        $this->post(route('employee.attendance.team.punch-selection', $member), [
            'date' => $today,
            'check_in_punch' => '1',
            'lunch_out_punch' => '3',
            'lunch_in_punch' => '4',
            'check_out_punch' => '5',
            'remarks' => 'Punch 2 was an accidental duplicate check-in.',
        ])
            ->assertRedirect(route('employee.attendance.team', ['date' => $today]));

        $this->assertDatabaseHas('supervisor_attendance_punch_selections', [
            'user_id' => 'EMP-2',
            'attendance_date' => $today,
            'check_in_punch' => 1,
            'lunch_out_punch' => 3,
            'lunch_in_punch' => 4,
            'check_out_punch' => 5,
            'remarks' => 'Punch 2 was an accidental duplicate check-in.',
            'approved_by' => $supervisor->account->id,
        ]);

        $response = $this->get(route('employee.attendance.team', ['date' => $today]))
            ->assertOk()
            ->assertSee('Approved by')
            ->assertSee('Edit Approval')
            ->assertSee('12:30')
            ->assertSee('13:00')
            ->assertSee('30 minutes')
            ->assertSee('17:30')
            ->assertSee('Punch 2 was an accidental duplicate check-in.')
            ->assertDontSee('<select', false);

        $this->post(route('employee.attendance.team.punch-selection', $other), [
            'date' => $today,
            'check_in_punch' => '1',
            'lunch_out_punch' => '3',
            'lunch_in_punch' => '4',
            'check_out_punch' => '5',
        ])->assertForbidden();
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
            $table->unsignedBigInteger('supervisor_employee_id');
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
        Schema::create('supervisor_attendance_punch_selections', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->date('attendance_date');
            $table->unsignedTinyInteger('check_in_punch')->nullable();
            $table->unsignedTinyInteger('lunch_out_punch')->nullable();
            $table->unsignedTinyInteger('lunch_in_punch')->nullable();
            $table->unsignedTinyInteger('check_out_punch')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('approved_by');
            $table->timestamps();
            $table->unique(['user_id', 'attendance_date']);
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
            'email' => $suffix.'@example.test',
            'password' => 'password',
            'role_id' => Role::where('slug', $roleSlug)->value('id'),
            'employee_id' => $employeeId,
        ]);
    }
}
