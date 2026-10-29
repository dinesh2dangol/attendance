<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class YearlyAttendanceSummary
{
    public static function for(Employee $employee, int $year): array
    {
        $yearStart = Carbon::create($year, 1, 1)->toDateString();
        $yearEnd = Carbon::create($year, 12, 31)->toDateString();
        $joinDate = $employee->join_date_eng
            ? Carbon::parse($employee->join_date_eng)->toDateString()
            : null;

        $attendanceQuery = DB::table('daily_attendance_step3')
            ->where('user_id', $employee->user_id)
            ->whereBetween('attendance_date', [$yearStart, $yearEnd])
            ->whereIn('attendance_status', ['Present', 'Remote'])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('leaves')
                    ->whereColumn('leaves.user_id', 'daily_attendance_step3.user_id')
                    ->whereColumn('leaves.leave_date', 'daily_attendance_step3.attendance_date');
            });

        if ($joinDate) {
            $attendanceQuery->whereDate('attendance_date', '>=', $joinDate);
        }

        $totalYearlyAttendance = (clone $attendanceQuery)->distinct()->count('attendance_date');

        $extraQuery = DB::table('daily_attendance_step3')
            ->where('user_id', $employee->user_id)
            ->whereBetween('attendance_date', [$yearStart, $yearEnd])
            ->whereIn('attendance_status', ['Present', 'Remote'])
            ->where(function ($query) use ($employee) {
                $query->where('day_type', 'Weekend')
                    ->orWhereExists(function ($holidayQuery) use ($employee) {
                        $holidayQuery->select(DB::raw(1))
                            ->from('holidays')
                            ->whereColumn('holidays.holiday_date', 'daily_attendance_step3.attendance_date')
                            ->where(function ($departmentQuery) use ($employee) {
                                $departmentQuery->whereNull('holidays.department_id')
                                    ->orWhere('holidays.department_id', $employee->department_id);
                            });
                    });
            });

        if ($joinDate) {
            $extraQuery->whereDate('attendance_date', '>=', $joinDate);
        }

        $yearlyExtraDates = (clone $extraQuery)
            ->distinct()
            ->orderBy('attendance_date')
            ->pluck('attendance_date')
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'))
            ->all();

        $absentQuery = DB::table('daily_attendance_step3')
            ->where('user_id', $employee->user_id)
            ->whereBetween('attendance_date', [$yearStart, $yearEnd])
            ->where('attendance_status', 'Absent')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('leaves')
                    ->whereColumn('leaves.user_id', 'daily_attendance_step3.user_id')
                    ->whereColumn('leaves.leave_date', 'daily_attendance_step3.attendance_date');
            });

        if ($joinDate) {
            $absentQuery->whereDate('attendance_date', '>=', $joinDate);
        }

        $yearlyAbsentDates = (clone $absentQuery)
            ->distinct()
            ->orderBy('attendance_date')
            ->pluck('attendance_date')
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'))
            ->all();

        $leaveQuery = DB::table('leaves')
            ->where('user_id', $employee->user_id)
            ->whereBetween('leave_date', [$yearStart, $yearEnd]);

        if ($joinDate) {
            $leaveQuery->whereDate('leave_date', '>=', $joinDate);
        }

        $yearlyLeaveRecords = (clone $leaveQuery)
            ->select('leave_date', 'leave_type', 'approval_status')
            ->orderBy('leave_date')
            ->get();

        $yearlyLeaveDates = $yearlyLeaveRecords
            ->map(fn ($leave) => [
                'date' => Carbon::parse($leave->leave_date)->format('Y-m-d'),
                'type' => match (strtolower($leave->leave_type)) {
                    'casual' => 'C',
                    'sick' => 'S',
                    default => null,
                },
                'pending' => (int) $leave->approval_status === 0,
            ])
            ->unique('date')
            ->values()
            ->all();

        $yearlyLeaveTotals = $yearlyLeaveRecords
            ->groupBy(fn ($leave) => strtolower($leave->leave_type))
            ->map(fn ($leaves) => $leaves->count())
            ->sortKeys();

        return [
            'totalYearlyAttendance' => $totalYearlyAttendance,
            'totalYearlyExtra' => count($yearlyExtraDates),
            'yearlyExtraDates' => $yearlyExtraDates,
            'totalYearlyAbsent' => count($yearlyAbsentDates),
            'yearlyAbsentDates' => $yearlyAbsentDates,
            'totalYearlyLeaves' => count($yearlyLeaveDates),
            'yearlyLeaveDates' => $yearlyLeaveDates,
            'yearlyLeaveTotals' => $yearlyLeaveTotals,
        ];
    }
}
