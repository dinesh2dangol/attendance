<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

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
        $status = (int) $request->input('status');

        $rules = [
            'user_id' => ['required', 'string', 'exists:employees,user_id'],
            'attendance_date' => ['required', 'date'],
            'status' => ['required', 'integer', 'in:0,1'],
            'remarks' => ['nullable', 'string'],
        ];

        if ($status === 1) {
            $rules['punch_IN'] = ['required', 'date_format:H:i'];
            $rules['punch_OUT'] = ['required', 'date_format:H:i'];
        }

        $validated = $request->validate($rules);

        if ($status === 1) {
            $punchTypes = ['IN', 'OUT', 'LUNCH_IN', 'LUNCH_OUT'];
            $rows = [];

            foreach ($punchTypes as $punchType) {
                $value = $request->input('punch_' . $punchType);

                if (empty($value)) {
                    continue;
                }

                $rows[] = [
                    'user_id' => $validated['user_id'],
                    'timestamp' => $validated['attendance_date'] . ' ' . $value . ':00',
                    'status' => 1,
                    'punch_type' => $punchType,
                    'remarks' => $validated['remarks'] ?? null,
                ];
            }

            if (empty($rows)) {
                return back()->withErrors(['status' => 'Please provide at least one punch timestamp for a present day.'])->withInput();
            }

            DB::table('ManualAttendance')->insert($rows);
        } else {
            $leaveDate = $validated['attendance_date'];

            DB::table('ManualAttendance')->insert([
                'user_id' => $validated['user_id'],
                'timestamp' => $leaveDate . ' 00:00:00',
                'status' => 0,
                'punch_type' => 'ABSENT',
                'remarks' => $validated['remarks'] ?? null,
            ]);

            DB::table('leaves')->updateOrInsert(
                [
                    'user_id' => $validated['user_id'],
                    'leave_date' => $leaveDate,
                ],
                [
                    'leave_type' => 'others',
                    'leave_description' => 'Manual attendance marked absent',
                    'approval_status' => 0,
                ]
            );
        }

        $employee = Employee::where('user_id', $validated['user_id'])->firstOrFail();
        $attendanceDate = Carbon::parse($validated['attendance_date']);

        return redirect()->route('employee.attendance', [
            'employee' => $employee,
            'month' => $attendanceDate->month,
            'year' => $attendanceDate->year,
        ])->with('success', 'Manual attendance added successfully.');
    }
}
