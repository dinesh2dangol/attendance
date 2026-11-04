<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Support\HolidayCalendar;
use App\Support\ManualAttendanceCategories;
use App\Support\SupervisorAttendancePunchSelections;
use App\Support\SupervisorGroupAccess;
use App\Support\WorkFromHomeCalendar;
use App\Support\YearlyAttendanceSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeePortalController extends Controller
{
    public function myGroups(Request $request)
    {
        $groups = SupervisorGroupAccess::groups($request->user());

        return view('employee.groups', compact('groups'));
    }

    public function teamAttendance(Request $request)
    {
        $supervisor = $request->user()->employee;
        $members = SupervisorGroupAccess::members($request->user())
            ->reject(fn ($member) => $member->user_id === $supervisor?->user_id)
            ->values();
        $validated = $request->validate([
            'date' => ['sometimes', 'required', 'date_format:Y-m-d'],
        ]);
        $attendanceDate = isset($validated['date'])
            ? Carbon::createFromFormat('Y-m-d', $validated['date'])
            : Carbon::today();
        $attendanceUserIds = $members->pluck('user_id')
            ->push($supervisor?->user_id)
            ->filter()
            ->unique()
            ->values();
        $attendanceByUser = $attendanceUserIds->isEmpty()
            ? collect()
            : DB::table('daily_attendance_step3')
                ->whereIn('user_id', $attendanceUserIds)
                ->whereDate('attendance_date', $attendanceDate->toDateString())
                ->get()
                ->keyBy('user_id');
        $punchSelectionsByUser = $attendanceUserIds->isEmpty()
            ? collect()
            : DB::table('supervisor_attendance_punch_selections')
                ->leftJoin('users as approvers', 'approvers.id', '=', 'supervisor_attendance_punch_selections.approved_by')
                ->whereIn('supervisor_attendance_punch_selections.user_id', $attendanceUserIds)
                ->whereDate('supervisor_attendance_punch_selections.attendance_date', $attendanceDate->toDateString())
                ->select('supervisor_attendance_punch_selections.*', 'approvers.name as approved_by_name')
                ->get()
                ->keyBy('user_id');
        SupervisorAttendancePunchSelections::apply(
            $attendanceByUser,
            $punchSelectionsByUser
        );
        $employeeGroup = null;
        $canReviewPunches = true;

        return view('employee.team-attendance', compact(
            'members',
            'supervisor',
            'attendanceDate',
            'attendanceByUser',
            'punchSelectionsByUser',
            'employeeGroup',
            'canReviewPunches'
        ));
    }

    public function exportTeamAttendance(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'employee_user_id' => ['sometimes', 'required', 'string', 'max:50'],
        ]);
        $supervisor = $request->user()->employee;
        $members = SupervisorGroupAccess::members($request->user())
            ->reject(fn ($member) => $member->user_id === $supervisor?->user_id);
        $selectedMember = null;
        if (isset($validated['employee_user_id'])) {
            $selectedMember = $supervisor && (string) $supervisor->user_id === $validated['employee_user_id']
                ? $supervisor
                : $members->first(
                    fn ($member) => (string) $member->user_id === $validated['employee_user_id']
                );
            abort_unless($selectedMember, 403);
        }
        $exportMembers = $selectedMember ? collect([$selectedMember]) : $members;
        $userIds = $exportMembers->pluck('user_id')
            ->when(
                $selectedMember === null || (string) $selectedMember->user_id !== (string) $supervisor?->user_id,
                fn ($ids) => $ids->push($supervisor?->user_id)
            )
            ->filter()
            ->unique()
            ->values();

        $attendanceRecords = $userIds->isEmpty()
            ? collect()
            : DB::table('daily_attendance_step3')
                ->whereIn('user_id', $userIds)
                ->whereBetween('attendance_date', [$validated['from'], $validated['to']])
                ->orderBy('attendance_date')
                ->get();
        $selections = $userIds->isEmpty()
            ? collect()
            : DB::table('supervisor_attendance_punch_selections')
                ->leftJoin('users as approvers', 'approvers.id', '=', 'supervisor_attendance_punch_selections.approved_by')
                ->whereIn('supervisor_attendance_punch_selections.user_id', $userIds)
                ->whereBetween('supervisor_attendance_punch_selections.attendance_date', [$validated['from'], $validated['to']])
                ->select('supervisor_attendance_punch_selections.*', 'approvers.name as approved_by_name')
                ->get();
        SupervisorAttendancePunchSelections::apply($attendanceRecords, $selections);

        $employeesByUserId = $exportMembers
            ->when(
                $selectedMember === null || (string) $selectedMember->user_id !== (string) $supervisor?->user_id,
                fn ($employees) => $employees->push($supervisor)
            )
            ->filter()
            ->keyBy('user_id');
        $selectionsByAttendance = $selections->keyBy(
            fn ($selection) => $selection->user_id.'|'.Carbon::parse($selection->attendance_date)->toDateString()
        );
        $filenamePrefix = $selectedMember
            ? 'employee-attendance-'.$selectedMember->id
            : 'team-attendance';
        $filename = sprintf('%s-%s-to-%s.csv', $filenamePrefix, $validated['from'], $validated['to']);

        return response()->streamDownload(function () use ($attendanceRecords, $employeesByUserId, $selectionsByAttendance): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Employee ID',
                'Employee',
                'Date',
                'Attendance',
                'Check In',
                'Lunch Out',
                'Lunch In',
                'Lunch Time (minutes)',
                'Check Out',
                'Approved By',
                'Remarks',
            ]);

            foreach ($attendanceRecords as $attendance) {
                $attendanceDate = Carbon::parse($attendance->attendance_date)->toDateString();
                $selection = $selectionsByAttendance->get($attendance->user_id.'|'.$attendanceDate);
                $lunchOut = data_get($attendance, 'supervisor_punch_selection_applied', false)
                    ? $attendance->lunch_out
                    : ($attendance->lunch_out ?? $attendance->punch_2 ?? null);
                $lunchIn = data_get($attendance, 'supervisor_punch_selection_applied', false)
                    ? $attendance->lunch_in
                    : ($attendance->lunch_in ?? $attendance->punch_3 ?? null);
                $lunchMinutes = filled($lunchOut) && filled($lunchIn)
                    ? intdiv((int) abs(Carbon::parse($lunchOut)->diffInSeconds(Carbon::parse($lunchIn))), 60)
                    : null;
                $employee = $employeesByUserId->get($attendance->user_id);

                fputcsv($output, [
                    $this->excelSafeCell($attendance->user_id),
                    $this->excelSafeCell($employee?->employee_name ?? ''),
                    $attendanceDate,
                    $this->excelSafeCell($attendance->attendance_status ?? ''),
                    $this->excelSafeCell($attendance->first_punch ?? ''),
                    $this->excelSafeCell($lunchOut ?? ''),
                    $this->excelSafeCell($lunchIn ?? ''),
                    $lunchMinutes,
                    $this->excelSafeCell($attendance->last_punch ?? ''),
                    $this->excelSafeCell($selection?->approved_by_name ?? ''),
                    $this->excelSafeCell($selection?->remarks ?? ''),
                ]);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function editTeamAttendancePunchSelection(Request $request, Employee $employee)
    {
        abort_unless(SupervisorGroupAccess::canReview($request->user(), $employee->user_id), 403);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);
        $attendance = DB::table('daily_attendance_step3')
            ->where('user_id', $employee->user_id)
            ->whereDate('attendance_date', $validated['date'])
            ->first();

        abort_unless($attendance, 404);

        $selection = DB::table('supervisor_attendance_punch_selections')
            ->leftJoin('users as approvers', 'approvers.id', '=', 'supervisor_attendance_punch_selections.approved_by')
            ->where('user_id', $employee->user_id)
            ->whereDate('attendance_date', $validated['date'])
            ->select('supervisor_attendance_punch_selections.*', 'approvers.name as approved_by_name')
            ->first();
        $selectedPunchNumbers = $selection
            ? [
                'check_in_punch' => $selection->check_in_punch,
                'lunch_out_punch' => $selection->lunch_out_punch,
                'lunch_in_punch' => $selection->lunch_in_punch,
                'check_out_punch' => $selection->check_out_punch,
            ]
            : SupervisorAttendancePunchSelections::suggestedPunchNumbers($attendance);
        $attendanceDate = Carbon::createFromFormat('Y-m-d', $validated['date']);

        return view('employee.team-attendance-punch-selection', compact(
            'employee',
            'attendance',
            'attendanceDate',
            'selection',
            'selectedPunchNumbers'
        ));
    }

    public function saveTeamAttendancePunchSelection(Request $request, Employee $employee)
    {
        abort_unless(SupervisorGroupAccess::canReview($request->user(), $employee->user_id), 403);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'check_in_punch' => ['nullable', 'integer', 'between:1,5'],
            'lunch_out_punch' => ['nullable', 'integer', 'between:1,5'],
            'lunch_in_punch' => ['nullable', 'integer', 'between:1,5'],
            'check_out_punch' => ['nullable', 'integer', 'between:1,5'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        $attendance = DB::table('daily_attendance_step3')
            ->where('user_id', $employee->user_id)
            ->whereDate('attendance_date', $validated['date'])
            ->first();

        abort_unless($attendance, 404);

        $selectedPunches = collect([
            'check_in_punch' => $validated['check_in_punch'] ?? null,
            'lunch_out_punch' => $validated['lunch_out_punch'] ?? null,
            'lunch_in_punch' => $validated['lunch_in_punch'] ?? null,
            'check_out_punch' => $validated['check_out_punch'] ?? null,
        ]);
        $assignedPunches = $selectedPunches->filter(fn ($punch) => $punch !== null);
        if ($assignedPunches->count() !== $assignedPunches->unique()->count()) {
            throw ValidationException::withMessages([
                'lunch_out_punch' => 'Each attendance punch can only be assigned to one attendance event.',
            ]);
        }

        foreach ($selectedPunches as $field => $punchNumber) {
            if ($punchNumber !== null && blank(data_get($attendance, 'punch_'.$punchNumber))) {
                throw ValidationException::withMessages([
                    $field => 'Select a punch that exists for this attendance date.',
                ]);
            }
        }

        $selectionKey = [
            'user_id' => $employee->user_id,
            'attendance_date' => $validated['date'],
        ];
        $existingSelection = DB::table('supervisor_attendance_punch_selections')
            ->where($selectionKey)
            ->exists();
        $selectionValues = [
            ...$selectedPunches->all(),
            'remarks' => $validated['remarks'] ?? null,
            'approved_by' => $request->user()->id,
            'updated_at' => now(),
        ];

        if ($existingSelection) {
            DB::table('supervisor_attendance_punch_selections')
                ->where($selectionKey)
                ->update($selectionValues);
        } else {
            DB::table('supervisor_attendance_punch_selections')
                ->insert($selectionValues + $selectionKey + ['created_at' => now()]);
        }

        return redirect()
            ->route('employee.attendance.team', ['date' => $validated['date']])
            ->with('success', 'Attendance punches approved.');
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
        $punchSelections = DB::table('supervisor_attendance_punch_selections')
            ->where('user_id', $employee->user_id)
            ->whereBetween('attendance_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get();
        SupervisorAttendancePunchSelections::apply($attendances, $punchSelections);

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

    private function excelSafeCell(?string $value): string
    {
        return preg_match('/^[\t\r\n ]*[=+\-@]/', $value ?? '') === 1
            ? "'".$value
            : ($value ?? '');
    }
}
