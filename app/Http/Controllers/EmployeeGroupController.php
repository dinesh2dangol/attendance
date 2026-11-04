<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeGroup;
use App\Models\Role;
use App\Support\SupervisorAttendancePunchSelections;
use App\Support\WorkFromHomeCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeGroupController extends Controller
{
    public function index()
    {
        $groups = EmployeeGroup::with(['supervisor', 'members'])
            ->orderBy('name')
            ->paginate(20);

        return view('employee-groups.index', compact('groups'));
    }

    public function attendance(Request $request, EmployeeGroup $employeeGroup)
    {
        $validated = $request->validate([
            'date' => ['sometimes', 'required', 'date_format:Y-m-d'],
        ]);
        $attendanceDate = isset($validated['date'])
            ? Carbon::createFromFormat('Y-m-d', $validated['date'])
            : Carbon::today();
        $supervisor = $employeeGroup->supervisor;
        $members = $employeeGroup->members()
            ->orderBy('employee_name')
            ->get();
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
        SupervisorAttendancePunchSelections::apply($attendanceByUser, $punchSelectionsByUser);
        $wfhStatusByUser = WorkFromHomeCalendar::statusesByUserForDate(
            $attendanceUserIds,
            $attendanceDate
        );
        $canReviewPunches = true;

        return view('employee.team-attendance', compact(
            'members',
            'supervisor',
            'attendanceDate',
            'attendanceByUser',
            'punchSelectionsByUser',
            'wfhStatusByUser',
            'employeeGroup',
            'canReviewPunches'
        ));
    }

    public function create()
    {
        return view('employee-groups.form', $this->formData());
    }

    public function store(Request $request)
    {
        $validated = $this->validateGroup($request);

        DB::transaction(function () use ($validated) {
            $group = EmployeeGroup::create([
                'name' => $validated['name'],
                'supervisor_employee_id' => $validated['supervisor_employee_id'],
            ]);

            $group->members()->sync($validated['members'] ?? []);
            $this->setSupervisor($group->supervisor);
        });

        return redirect()->route('employee-groups.index')->with('success', 'Employee group created.');
    }

    public function edit(EmployeeGroup $employeeGroup)
    {
        $employeeGroup->load('members');

        return view('employee-groups.form', $this->formData($employeeGroup) + [
            'employeeGroup' => $employeeGroup,
        ]);
    }

    public function update(Request $request, EmployeeGroup $employeeGroup)
    {
        $validated = $this->validateGroup($request, $employeeGroup);

        DB::transaction(function () use ($validated, $employeeGroup) {
            $previousSupervisor = $employeeGroup->supervisor;
            $newSupervisor = Employee::findOrFail($validated['supervisor_employee_id']);
            $employeeGroup->update([
                'name' => $validated['name'],
                'supervisor_employee_id' => $validated['supervisor_employee_id'],
            ]);

            $employeeGroup->members()->sync($validated['members'] ?? []);
            $this->setSupervisor($newSupervisor);

            if ($previousSupervisor && $previousSupervisor->id !== (int) $validated['supervisor_employee_id']) {
                $this->restoreEmployeeRoleIfNoLongerSupervisor($previousSupervisor);
            }
        });

        return redirect()->route('employee-groups.index')->with('success', 'Employee group updated.');
    }

    private function validateGroup(Request $request, ?EmployeeGroup $group = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('employee_groups', 'name')->ignore($group?->id)],
            'supervisor_employee_id' => ['required', 'integer', 'exists:employees,id'],
            'members' => ['nullable', 'array'],
            'members.*' => ['integer', 'distinct', 'exists:employees,id'],
        ]);

        $supervisorId = (int) $validated['supervisor_employee_id'];
        $members = collect($validated['members'] ?? [])->map(fn ($id) => (int) $id);

        if ($members->contains($supervisorId)) {
            throw ValidationException::withMessages([
                'members' => 'The supervisor cannot also be assigned as a regular group member.',
            ]);
        }

        $supervisor = Employee::query()
            ->whereKey($supervisorId)
            ->whereHas('account.role', fn ($query) => $query->whereIn('slug', ['employee', 'supervisor']))
            ->exists();

        if (! $supervisor) {
            throw ValidationException::withMessages([
                'supervisor_employee_id' => 'Select an employee account that can supervise groups.',
            ]);
        }

        $validMemberCount = Employee::query()
            ->whereKey($members)
            ->where(function ($query) {
                $query->whereDoesntHave('account')
                    ->orWhereHas('account.role', fn ($query) => $query->where('slug', 'employee'));
            })
            ->count();

        if ($validMemberCount !== $members->unique()->count()) {
            throw ValidationException::withMessages([
                'members' => 'Group members must be regular employees.',
            ]);
        }

        return $validated;
    }

    private function formData(?EmployeeGroup $group = null): array
    {
        $supervisors = Employee::query()
            ->whereHas('account.role', fn ($query) => $query->whereIn('slug', ['employee', 'supervisor']))
            ->with('account')
            ->orderBy('employee_name')
            ->get();
        $members = Employee::query()
            ->where(function ($query) {
                $query->whereDoesntHave('account')
                    ->orWhereHas('account.role', fn ($query) => $query->where('slug', 'employee'));
            })
            ->with('account')
            ->orderBy('employee_name')
            ->get();

        return compact('supervisors', 'members');
    }

    private function setSupervisor(Employee $supervisor): void
    {
        $roleId = Role::where('slug', 'supervisor')->value('id');
        abort_unless($roleId, 500, 'Supervisor role is not configured. Run migrations.');

        $supervisor->groups()->detach();
        $supervisor->account()->update(['role_id' => $roleId]);
    }

    private function restoreEmployeeRoleIfNoLongerSupervisor(Employee $employee): void
    {
        if ($employee->supervisedGroups()->exists()) {
            return;
        }

        $roleId = Role::where('slug', 'employee')->value('id');
        $supervisorRoleId = Role::where('slug', 'supervisor')->value('id');
        if ($roleId && $supervisorRoleId) {
            $employee->account()->where('role_id', $supervisorRoleId)->update(['role_id' => $roleId]);
        }
    }
}
