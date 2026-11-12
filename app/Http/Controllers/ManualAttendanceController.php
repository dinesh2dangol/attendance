<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Support\ManualAttendanceCategories;
use App\Support\SupervisorGroupAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ManualAttendanceController extends Controller
{
    public function create(Request $request)
    {
        $selectedUserId = $request->query('user_id');
        $selectedTimestamp = $request->query('timestamp');
        $selectedStatus = $request->query('status');
        $selectedReason = $request->query('reason');

        if ($request->user()->role?->slug === 'supervisor') {
            $validated = $request->validate([
                'user_id' => ['required', 'string', 'exists:employees,user_id'],
                'timestamp' => ['required', 'date'],
                'status' => ['required', 'integer', 'in:1'],
                'reason' => ['required', 'string', Rule::in(['WFH'])],
            ]);
            $selectedUserId = $validated['user_id'];
            $selectedTimestamp = $validated['timestamp'];
            $selectedStatus = (string) $validated['status'];
            $selectedReason = $validated['reason'];

            $this->authorizeApprovedWfhAttendance(
                $request,
                $selectedUserId,
                Carbon::parse($selectedTimestamp)->toDateString(),
                1,
                'WFH'
            );
            $employees = Employee::whereIn(
                'user_id',
                SupervisorGroupAccess::memberUserIds($request->user())
            )->orderBy('employee_name')->get();
        } else {
            $employees = Employee::orderBy('employee_name')->get();
        }

        return view('manual-attendance.create', [
            'employees' => $employees,
            'selectedUserId' => $selectedUserId,
            'selectedTimestamp' => $selectedTimestamp,
            'selectedStatus' => $selectedStatus,
            'selectedReason' => $selectedReason,
            'reasons' => ManualAttendanceCategories::REASONS,
        ]);
    }

    public function store(Request $request)
    {
        $status = (int) $request->input('status');

        $rules = [
            'user_id' => ['required', 'string', 'exists:employees,user_id'],
            'attendance_date' => ['required', 'date'],
            'status' => ['required', 'integer', 'in:0,1'],
            'reason' => ['required', 'string', Rule::in(ManualAttendanceCategories::REASONS)],
            'remarks' => ['nullable', 'string'],
        ];

        if ($status === 1) {
            $rules['punch_IN'] = ['required', 'date_format:H:i'];
            $rules['punch_OUT'] = ['required', 'date_format:H:i'];
        }

        $validated = $request->validate($rules);
        $this->authorizeApprovedWfhAttendance(
            $request,
            $validated['user_id'],
            Carbon::parse($validated['attendance_date'])->toDateString(),
            (int) $validated['status'],
            $validated['reason']
        );

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
                    'category' => $validated['reason'],
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
                'category' => $validated['reason'],
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

    private function authorizeApprovedWfhAttendance(
        Request $request,
        string $userId,
        string $attendanceDate,
        int $status,
        string $reason
    ): void {
        if ($request->user()->role?->slug !== 'supervisor') {
            return;
        }

        abort_unless(
            $status === 1
                && $reason === 'WFH'
                && SupervisorGroupAccess::canReview($request->user(), $userId)
                && DB::table('work_from_home_requests')
                    ->where('user_id', $userId)
                    ->where('status', 'approved')
                    ->whereDate('start_date', '<=', $attendanceDate)
                    ->whereDate('end_date', '>=', $attendanceDate)
                    ->exists(),
            403
        );
    }
}
