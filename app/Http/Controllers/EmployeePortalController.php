<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Support\SupervisorGroupAccess;
use App\Support\HolidayCalendar;
use App\Support\ManualAttendanceCategories;
use App\Support\YearlyAttendanceSummary;
use App\Support\WorkFromHomeCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeePortalController extends Controller
{
    public function myGroups(Request $request)
    {
        $groups = SupervisorGroupAccess::groups($request->user());

        return view('employee.groups', compact('groups'));
    }

    public function teamAttendance(Request $request)
    {
        $members = SupervisorGroupAccess::members($request->user());
        $today = Carbon::today();
        $memberUserIds = $members->pluck('user_id')->filter()->unique()->values();
        $todayAttendanceByUser = $memberUserIds->isEmpty()
            ? collect()
            : DB::table('daily_attendance_step3')
                ->whereIn('user_id', $memberUserIds)
                ->whereDate('attendance_date', $today->toDateString())
                ->select('user_id', 'attendance_status', 'first_punch', 'last_punch')
                ->get()
                ->keyBy('user_id');

        return view('employee.team-attendance', compact('members', 'today', 'todayAttendanceByUser'));
    }

    public function attendance(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'Your account is not linked to an employee record.');

        $month = (int) $request->query('month', Carbon::now()->month);
        $year = Carbon::now()->year;
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

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
        $holidaysByDate = HolidayCalendar::byDate($employee, $monthStart, $monthEnd);
        $approvedWfhDates = WorkFromHomeCalendar::approvedDatesByDate(
            $employee->user_id,
            $monthStart,
            $monthEnd
        );
        $pendingWfhDates = WorkFromHomeCalendar::pendingDatesByDate(
            $employee->user_id,
            $monthStart,
            $monthEnd
        );

        $yearlySummary = YearlyAttendanceSummary::for($employee, $year);

        return view('attendance', compact(
            'employee',
            'attendances',
            'leavesByDate',
            'monthStart',
            'monthEnd',
            'holidaysByDate',
            'approvedWfhDates',
            'pendingWfhDates',
            'manualAttendanceByDate',
            'year'
        ) + $yearlySummary + ['isOwnAttendance' => true]);
    }
}
