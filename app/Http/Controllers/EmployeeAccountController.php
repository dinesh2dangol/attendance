<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmployeeAccountController extends Controller
{
    public function index()
    {
        $employees = Employee::with(['department', 'account'])
            ->orderBy('employee_name')
            ->get();

        return view('employee-accounts.index', compact('employees'));
    }

    public function store(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($employee->account?->id),
            ],
        ]);

        $temporaryPassword = Str::random(12);
        $account = $employee->account ?: $employee->account()->make();
        $account->name = $employee->employee_name;
        $account->email = $validated['email'];
        $account->password = Hash::make($temporaryPassword);
        $account->role_id = Role::where('slug', 'employee')->value('id');
        $account->save();

        return redirect()->route('employee-accounts.index')->with('credentials', [
            'employee' => $employee->employee_name,
            'email' => $account->email,
            'password' => $temporaryPassword,
        ]);
    }
}
