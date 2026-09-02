<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()->role?->slug === 'employee') {
            return redirect()->route('employee.attendance.self');
        }

        $departments = Department::orderBy('department_name')->get();
        $defaultDepartmentId = $departments->skip(3)->first()?->department_id;

        if (count($request->query()) === 0) {
            return redirect()->route('dashboard', array_filter([
                'department' => $defaultDepartmentId,
                'status' => '1',
            ]));
        }

        $search = $request->query('search');
        $gender = $request->query('gender');
        $department = $request->query('department');
        $status = $request->query('status');
        $perPage = in_array((int) $request->query('per_page', 10), [10, 25, 50, 100], true)
            ? (int) $request->query('per_page', 10)
            : 10;

        $query = Employee::orderBy('employee_name')->whereHas('department');

        if ($search) {
            $query->where('employee_name', 'like', "%{$search}%");
        }

        if ($gender) {
            $query->where('gender', $gender);
        }

        if ($department !== null && $department !== '') {
            $query->where('department_id', $department);
        }

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        $employees = $query->with('department')->paginate($perPage)->withQueryString();
        $currentYear = Carbon::now()->year;

        $joinDatesByUser = $employees->getCollection()
            ->mapWithKeys(fn ($employee) => [$employee->user_id => $employee->join_date_eng ? Carbon::parse($employee->join_date_eng)->startOfDay() : null])
            ->all();

        $absentDatesByUser = DB::table('daily_attendance_step3')
            ->where('attendance_status', 'Absent')
            ->whereYear('attendance_date', $currentYear)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('leaves')
                    ->whereColumn('leaves.user_id', 'daily_attendance_step3.user_id')
                    ->whereColumn('leaves.leave_date', 'daily_attendance_step3.attendance_date');
            })
            ->select('user_id', 'attendance_date')
            ->orderBy('attendance_date')
            ->get()
            ->groupBy('user_id')
            ->map(function ($rows, $userId) use ($joinDatesByUser) {
                $joinDate = $joinDatesByUser[$userId] ?? null;

                return $rows
                    ->pluck('attendance_date')
                    ->filter(fn ($date) => ! $joinDate || Carbon::parse($date)->greaterThanOrEqualTo($joinDate))
                    ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'))
                    ->values()
                    ->all();
            })
            ->all();

        $leaveDatesByUser = DB::table('leaves')
            ->whereYear('leave_date', $currentYear)
            ->select('user_id', 'leave_date', 'approval_status')
            ->orderBy('leave_date')
            ->get()
            ->groupBy('user_id')
            ->map(function ($rows, $userId) use ($joinDatesByUser) {
                $joinDate = $joinDatesByUser[$userId] ?? null;

                return $rows
                    ->filter(fn ($row) => ! $joinDate || Carbon::parse($row->leave_date)->greaterThanOrEqualTo($joinDate))
                    ->map(fn ($row) => [
                        'date' => Carbon::parse($row->leave_date)->format('Y-m-d'),
                        'pending' => (int) $row->approval_status === 0,
                    ])
                    ->values()
                    ->all();
            })
            ->all();

        return view('dashboard', compact(
            'employees',
            'departments',
            'department',
            'status',
            'gender',
            'search',
            'perPage',
            'absentDatesByUser',
            'leaveDatesByUser'
        ));
    }
}
