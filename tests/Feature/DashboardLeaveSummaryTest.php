<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardLeaveSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_leave_totals_by_category_for_each_employee(): void
    {
        $leaveYear = now()->year;

        Schema::create('daily_attendance_step3', function (Blueprint $table) {
            $table->string('user_id');
            $table->date('attendance_date');
            $table->string('attendance_status');
            $table->string('day_type')->nullable();
        });

        $department = Department::create(['department_name' => 'Operations']);
        $admin = User::factory()->create([
            'role_id' => Role::where('slug', 'admin')->value('id'),
        ]);
        $employee = Employee::create([
            'user_id' => 'EMP-001',
            'employee_name' => 'First Employee',
            'status' => 1,
            'department_id' => $department->department_id,
        ]);
        Employee::create([
            'user_id' => 'EMP-002',
            'employee_name' => 'Second Employee',
            'status' => 1,
            'department_id' => $department->department_id,
        ]);

        DB::table('daily_attendance_step3')->insert([
            [
                'user_id' => $employee->user_id,
                'attendance_date' => "{$leaveYear}-01-10",
                'attendance_status' => 'Absent',
            ],
            [
                'user_id' => $employee->user_id,
                'attendance_date' => "{$leaveYear}-01-13",
                'attendance_status' => 'Absent',
            ],
            [
                'user_id' => $employee->user_id,
                'attendance_date' => "{$leaveYear}-01-14",
                'attendance_status' => 'Absent',
            ],
            [
                'user_id' => $employee->user_id,
                'attendance_date' => "{$leaveYear}-01-17",
                'attendance_status' => 'Present',
                'day_type' => 'Weekend',
            ],
        ]);

        Leave::create([
            'user_id' => $employee->user_id,
            'leave_date' => "{$leaveYear}-01-10",
            'leave_type' => 'sick',
            'approval_status' => 0,
        ]);
        Leave::create([
            'user_id' => $employee->user_id,
            'leave_date' => "{$leaveYear}-01-11",
            'leave_type' => 'sick',
            'approval_status' => 1,
        ]);
        Leave::create([
            'user_id' => $employee->user_id,
            'leave_date' => "{$leaveYear}-01-12",
            'leave_type' => 'casual',
            'approval_status' => 1,
        ]);
        Leave::create([
            'user_id' => 'EMP-002',
            'leave_date' => "{$leaveYear}-01-13",
            'leave_type' => 'maternity',
            'approval_status' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard', [
                'department' => $department->department_id,
                'status' => 1,
            ]))
            ->assertOk()
            ->assertSee('Leave Totals by Category')
            ->assertSee('Sick: 2')
            ->assertSee('Casual: 1')
            ->assertSee('Total Leaves:</strong> 3')
            ->assertSee('Total Absent Dates:</strong> 2')
            ->assertSee('Total Absent Dates</span>')
            ->assertSee('Extra Dates:')
            ->assertSee("{$leaveYear}-01-17")
            ->assertSee('Total Extra:</strong> 1')
            ->assertSee('Maternity: 1')
            ->assertSee('Total Leaves</span>')
            ->assertSee("{$leaveYear}-01-10 (S) (P)")
            ->assertSee("{$leaveYear}-01-11 (S)")
            ->assertSee("{$leaveYear}-01-12 (C)")
            ->assertSee('Second Employee');
    }
}
