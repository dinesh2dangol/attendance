<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Support\YearlyAttendanceSummary;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class YearlyAttendanceSummaryTest extends TestCase
{
    public function test_it_returns_yearly_absence_leave_and_extra_date_summaries(): void
    {
        Schema::create('daily_attendance_step3', function (Blueprint $table) {
            $table->string('user_id');
            $table->date('attendance_date');
            $table->string('attendance_status');
            $table->string('day_type');
        });
        Schema::create('leaves', function (Blueprint $table) {
            $table->string('user_id');
            $table->date('leave_date');
            $table->string('leave_type');
            $table->integer('approval_status');
        });
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date');
            $table->string('holiday_name');
            $table->unsignedBigInteger('department_id')->nullable();
        });

        $employee = new Employee([
            'user_id' => 'EMP-001',
            'department_id' => 12,
            'join_date_eng' => '2025-01-01',
        ]);

        DB::table('daily_attendance_step3')->insert([
            [
                'user_id' => 'EMP-001',
                'attendance_date' => '2026-01-05',
                'attendance_status' => 'Present',
                'day_type' => 'Weekday',
            ],
            [
                'user_id' => 'EMP-001',
                'attendance_date' => '2026-01-10',
                'attendance_status' => 'Present',
                'day_type' => 'Weekend',
            ],
            [
                'user_id' => 'EMP-001',
                'attendance_date' => '2026-01-11',
                'attendance_status' => 'Absent',
                'day_type' => 'Weekday',
            ],
            [
                'user_id' => 'EMP-001',
                'attendance_date' => '2026-01-12',
                'attendance_status' => 'Absent',
                'day_type' => 'Weekday',
            ],
        ]);

        DB::table('holidays')->insert([
            'holiday_date' => '2026-01-05',
            'holiday_name' => 'Company Holiday',
            'department_id' => 12,
        ]);

        DB::table('leaves')->insert([
            [
                'user_id' => 'EMP-001',
                'leave_date' => '2026-01-11',
                'leave_type' => 'casual',
                'approval_status' => 1,
            ],
            [
                'user_id' => 'EMP-001',
                'leave_date' => '2026-01-13',
                'leave_type' => 'sick',
                'approval_status' => 0,
            ],
        ]);

        $summary = YearlyAttendanceSummary::for($employee, 2026);

        $this->assertSame(2, $summary['totalYearlyAttendance']);
        $this->assertSame(2, $summary['totalYearlyExtra']);
        $this->assertSame(['2026-01-05', '2026-01-10'], $summary['yearlyExtraDates']);
        $this->assertSame(1, $summary['totalYearlyAbsent']);
        $this->assertSame(['2026-01-12'], $summary['yearlyAbsentDates']);
        $this->assertSame(2, $summary['totalYearlyLeaves']);
        $this->assertSame([
            ['date' => '2026-01-11', 'type' => 'C', 'pending' => false],
            ['date' => '2026-01-13', 'type' => 'S', 'pending' => true],
        ], $summary['yearlyLeaveDates']);
        $this->assertSame(['casual' => 1, 'sick' => 1], $summary['yearlyLeaveTotals']->all());
    }
}
