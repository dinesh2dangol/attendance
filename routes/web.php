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
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\ManualAttendanceController;

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

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard');

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
})->middleware('auth')->name('employee.attendance');

Route::middleware('auth')->group(function () {
    Route::get('leaves', [LeaveController::class, 'index'])->name('leaves.index');
    Route::get('leaves/create', [LeaveController::class, 'create'])->name('leaves.create');
    Route::post('leaves', [LeaveController::class, 'store'])->name('leaves.store');
    Route::get('leaves/{leave}/edit', [LeaveController::class, 'edit'])->name('leaves.edit');
    Route::put('leaves/{leave}', [LeaveController::class, 'update'])->name('leaves.update');
    Route::get('leaves/approval', [LeaveController::class, 'approval'])->name('leaves.approval');
    Route::post('leaves/{leave}/approve', [LeaveController::class, 'approve'])->name('leaves.approve');

    Route::get('manual-attendance/create', [ManualAttendanceController::class, 'create'])->name('manual-attendance.create');
    Route::post('manual-attendance', [ManualAttendanceController::class, 'store'])->name('manual-attendance.store');
});
