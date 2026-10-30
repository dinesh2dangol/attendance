<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeGroup;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class EmployeeGroupController extends Controller
{
    public function index()
    {
        $groups = EmployeeGroup::with(['supervisor', 'members'])
            ->orderBy('name')
            ->paginate(20);

        return view('employee-groups.index', compact('groups'));
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
            ->whereDoesntHave('supervisedGroup', fn ($query) => $query->when(
                $group,
                fn ($query) => $query->where('id', '!=', $group->id)
            ))
            ->exists();

        if (! $supervisor) {
            throw ValidationException::withMessages([
                'supervisor_employee_id' => 'Select an employee account that is not already supervising another group.',
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
            ->where(function ($query) use ($group) {
                $query->whereDoesntHave('supervisedGroup');
                if ($group) {
                    $query->orWhere('employees.id', $group->supervisor_employee_id);
                }
            })
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
        if ($employee->supervisedGroup()->exists()) {
            return;
        }

        $roleId = Role::where('slug', 'employee')->value('id');
        $supervisorRoleId = Role::where('slug', 'supervisor')->value('id');
        if ($roleId && $supervisorRoleId) {
            $employee->account()->where('role_id', $supervisorRoleId)->update(['role_id' => $roleId]);
        }
    }
}
