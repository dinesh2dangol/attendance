<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Carbon;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::view('login', 'auth.login')->name('login');

    Route::post('login', function (Request $request) {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors([
                    'email' => 'The provided credentials do not match our records.',
                ])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    });

    // Route::view('register', 'auth.register')->name('register');

    Route::post('register', function (Request $request) {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    });
});

Route::post('logout', function (Request $request) {
    Auth::guard()->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/');
})->middleware('auth')->name('logout');

Route::get('dashboard', function (Request $request) {
    $departments = Department::orderBy('department_name')->get();

    // Pick the 2nd department if available (index 1), fallback to null
    $defaultDepartmentId = $departments->skip(3)->first()?->department_id;

    // Check if user has submitted any filter or pagination query params
    $hasQueryParams = count($request->query()) > 0;

    // Set defaults on initial load (when no query parameters exist)
    $gender = $request->query('gender');
    $department = $hasQueryParams ? $request->query('department') : $defaultDepartmentId;
    $status = $hasQueryParams ? $request->query('status') : '1'; // '1' is Active

    // Build Query
    $query = Employee::orderBy('employee_name');

    if ($search = $request->query('search')) {
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

    $employees = $query->with('department')->paginate(10)->withQueryString();

    // Pass resolved filter values to view so dropdowns reflect defaults
    return view('dashboard', compact('employees', 'departments', 'department', 'status', 'gender'));
})->middleware('auth')->name('dashboard');

Route::get('employees/{employee}/edit', function (App\Models\Employee $employee) {
    $departments = Department::orderBy('department_name')->get();

    return view('employee.edit', compact('employee', 'departments'));
})->middleware('auth')->name('employees.edit');

Route::put('employees/{employee}', function (App\Models\Employee $employee, Request $request) {
    $validated = $request->validate([
        'employee_name' => ['required', 'string', 'max:50'],
        'join_date_eng' => ['nullable', 'date'],
        'join_date_npt' => ['nullable', 'string', 'max:20'],
        'status' => ['nullable', 'integer'],
        'salary' => ['nullable', 'numeric'],
        'working_hours' => ['nullable', 'numeric'],
        'part_time' => ['nullable', 'boolean'],
        'department_id' => ['nullable', 'integer', 'exists:departments,department_id'],
        'gender' => ['nullable', 'string', 'max:8'],
    ]);

    $employee->update($validated);

    return redirect()->route('dashboard')->with('success', 'Employee updated successfully.');
})->middleware('auth')->name('employees.update');

Route::get('employees/{employee}/attendance', function (App\Models\Employee $employee, Request $request) {
    $month = intval($request->query('month', Carbon::now()->month));
    $year = intval($request->query('year', Carbon::now()->year));
    $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
    $monthEnd = $monthStart->copy()->endOfMonth();

    DB::statement("SET collation_connection = 'utf8mb4_0900_ai_ci'");

    $attendances = DB::table('wiseyak_everyday_v2')
        ->where('user_id', $employee->user_id)
        ->whereBetween('attendance_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
        ->orderBy('attendance_date')
        ->get();

    return view('attendance', compact('employee', 'attendances', 'monthStart', 'monthEnd'));
})->middleware('auth')->name('employee.attendance');
