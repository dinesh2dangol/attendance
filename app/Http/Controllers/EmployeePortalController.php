<?php

namespace App\Http\Controllers;

use App\Models\Employee;
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
        $year = (int) $request->query('year', Carbon::now()->year);
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

        return view('attendance', compact('employee', 'attendances', 'leavesByDate', 'monthStart', 'monthEnd'));
    }
}
