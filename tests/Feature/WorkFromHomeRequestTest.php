<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorkFromHomeRequestTest extends TestCase
{
    public function test_employee_can_submit_a_date_range_for_admin_approval(): void
    {
        $this->createRequestSchema();
        $employeeUser = $this->createUser('employee', 'employee');
        Employee::create([
            'id' => 1,
            'user_id' => 'EMP-1',
            'employee_name' => 'Test Employee',
        ]);
        $employeeUser->employee_id = 1;
        $employeeUser->save();
        DB::table('leaves')->insert([
            'user_id' => 'EMP-1',
            'leave_date' => '2026-11-02',
            'leave_type' => 'casual',
            'approval_status' => 0,
        ]);

        $this->actingAs($employeeUser)
            ->from(route('employee.wfh.create'))
            ->post(route('employee.wfh.store'), [
                'start_date' => '2026-11-02',
                'end_date' => '2026-11-03',
                'reason' => 'Home repair appointment',
            ])
            ->assertSessionHasErrors('start_date');

        $this->post(route('employee.wfh.store'), [
            'start_date' => '2026-11-03',
            'end_date' => '2026-11-04',
            'reason' => 'Home repair appointment',
        ])->assertRedirect(route('employee.wfh.index'));

        $this->assertSame(
            1,
            DB::table('work_from_home_requests')
                ->where('user_id', 'EMP-1')
                ->whereDate('start_date', '2026-11-03')
                ->whereDate('end_date', '2026-11-04')
                ->where('status', 'pending')
                ->count()
        );
    }

    public function test_admin_can_approve_a_pending_request(): void
    {
        $this->createRequestSchema();
        $admin = $this->createUser('admin', 'admin');
        $requestId = DB::table('work_from_home_requests')->insertGetId([
            'user_id' => 'EMP-1',
            'start_date' => '2026-11-03',
            'end_date' => '2026-11-04',
            'reason' => 'Home repair appointment',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('wfh-requests.approve', $requestId))
            ->assertRedirect(route('wfh-requests.index'));

        $this->assertDatabaseHas('work_from_home_requests', [
            'id' => $requestId,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);
    }

    public function test_superadmin_can_submit_a_pending_request_for_an_employee(): void
    {
        $this->createRequestSchema();
        $superadmin = $this->createUser('superadmin', 'superadmin');
        Employee::create([
            'id' => 1,
            'user_id' => 'EMP-1',
            'employee_name' => 'Test Employee',
        ]);

        $this->actingAs($superadmin)
            ->get(route('wfh-requests.create'))
            ->assertOk()
            ->assertSee('Test Employee');

        $this->post(route('wfh-requests.store'), [
            'user_id' => 'EMP-1',
            'start_date' => '2026-11-03',
            'end_date' => '2026-11-04',
            'reason' => 'Employee requested WFH',
        ])->assertRedirect(route('wfh-requests.index'));

        $this->assertSame(
            1,
            DB::table('work_from_home_requests')
                ->where('user_id', 'EMP-1')
                ->whereDate('start_date', '2026-11-03')
                ->whereDate('end_date', '2026-11-04')
                ->where('status', 'pending')
                ->count()
        );
    }

    private function createRequestSchema(): void
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
        Schema::create('leaves', function (Blueprint $table) {
            $table->id('leave_id');
            $table->string('user_id')->nullable();
            $table->date('leave_date');
            $table->string('leave_type');
            $table->string('leave_description')->nullable();
            $table->integer('approval_status')->default(0);
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
        ]);
    }

    private function createUser(string $roleSlug, string $suffix): User
    {
        $roleId = Role::where('slug', $roleSlug)->value('id');

        return User::create([
            'name' => ucfirst($suffix),
            'email' => $suffix . '@example.test',
            'password' => 'password',
            'role_id' => $roleId,
        ]);
    }
}
