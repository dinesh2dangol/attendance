<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Support\ManualAttendanceCategories;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeePortalController extends Controller
{
    public function attendance(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'Your account is not linked to an employee record.');

        $month = (int) $request->query('month', Carbon::now()->month);
        $year = Carbon::now()->year;
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $yearStart = Carbon::create($year, 1, 1)->toDateString();
        $yearEnd = Carbon::create($year, 12, 31)->toDateString();

        DB::statement("SET collation_connection = 'utf8mb4_0900_ai_ci'");

        $attendances = DB::table('daily_attendance_step3')
            ->where('user_id', $employee->user_id)
            ->whereBetween('attendance_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->orderBy('attendance_date')
            ->get();

        $leavesByDate = DB::table('leaves')
            ->where('user_id', $employee->user_id)
            ->whereBetween('leave_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get()
            ->keyBy(fn ($leave) => Carbon::parse($leave->leave_date)->format('Y-m-d'));
        $manualAttendanceByDate = ManualAttendanceCategories::byDate(
            $employee->user_id,
            $monthStart,
            $monthEnd
        );

        $yearlyAttendanceQuery = DB::table('daily_attendance_step3')
            ->where('user_id', $employee->user_id)
            ->whereBetween('attendance_date', [$yearStart, $yearEnd])
            ->whereIn('attendance_status', ['Present', 'Remote'])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('leaves')
                    ->whereColumn('leaves.user_id', 'daily_attendance_step3.user_id')
                    ->whereColumn('leaves.leave_date', 'daily_attendance_step3.attendance_date');
            });

        if ($employee->join_date_eng) {
            $yearlyAttendanceQuery->whereDate(
                'attendance_date',
                '>=',
                Carbon::parse($employee->join_date_eng)->toDateString()
            );
        }

        $totalYearlyAttendance = $yearlyAttendanceQuery->distinct()->count('attendance_date');

        $yearlyAbsentQuery = DB::table('daily_attendance_step3')
            ->where('user_id', $employee->user_id)
            ->whereBetween('attendance_date', [$yearStart, $yearEnd])
            ->where('attendance_status', 'Absent')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('leaves')
                    ->whereColumn('leaves.user_id', 'daily_attendance_step3.user_id')
                    ->whereColumn('leaves.leave_date', 'daily_attendance_step3.attendance_date');
            });

        if ($employee->join_date_eng) {
            $yearlyAbsentQuery->whereDate(
                'attendance_date',
                '>=',
                Carbon::parse($employee->join_date_eng)->toDateString()
            );
        }

        $totalYearlyAbsent = $yearlyAbsentQuery->distinct()->count('attendance_date');

        $yearlyLeaveQuery = DB::table('leaves')
            ->where('user_id', $employee->user_id)
            ->whereBetween('leave_date', [$yearStart, $yearEnd]);

        if ($employee->join_date_eng) {
            $yearlyLeaveQuery->whereDate(
                'leave_date',
                '>=',
                Carbon::parse($employee->join_date_eng)->toDateString()
            );
        }

        $totalYearlyLeaves = $yearlyLeaveQuery->distinct()->count('leave_date');

        return view('attendance', compact(
            'employee',
            'attendances',
            'leavesByDate',
            'monthStart',
            'monthEnd',
            'manualAttendanceByDate',
            'year',
            'totalYearlyAttendance',
            'totalYearlyAbsent',
            'totalYearlyLeaves'
        ));
    }
}
