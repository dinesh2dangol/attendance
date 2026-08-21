<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManualAttendanceController extends Controller
{
    public function create(Request $request)
    {
        $employees = Employee::orderBy('employee_name')->get();

        return view('manual-attendance.create', [
            'employees' => $employees,
            'selectedUserId' => $request->query('user_id'),
            'selectedTimestamp' => $request->query('timestamp'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string', 'exists:employees,user_id'],
            'timestamp' => ['required', 'date'],
            'status' => ['required', 'integer', 'in:0,1'],
            'punch_type' => ['required', 'string', 'max:50'],
            'remarks' => ['nullable', 'string'],
        ]);

        DB::table('ManualAttendance')->insert([
            'user_id' => $validated['user_id'],
            'timestamp' => $validated['timestamp'],
            'status' => $validated['status'],
            'punch_type' => $validated['punch_type'],
            'remarks' => $validated['remarks'] ?? null,
        ]);

        return redirect()->route('dashboard')->with('success', 'Manual attendance added successfully.');
    }
}
