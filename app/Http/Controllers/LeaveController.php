<?php

namespace App\Http\Controllers;

use App\Models\Leave;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class LeaveController extends Controller
{
    protected array $leaveTypes = [
        'sick',
        'casual',
        'maternity',
        'paternity',
        'unpaid',
        'others',
    ];

    protected function getLeaveTypes(): array
    {
        return $this->leaveTypes;
    }

    public function index(Request $request)
    {
        $query = Leave::with('employee')->orderBy('leave_date', 'desc');

        $userId = $request->query('user_id');
        $filterEmployee = null;

        if ($userId) {
            $query->where('user_id', $userId);
            $filterEmployee = Employee::where('user_id', $userId)->first();
        }

        $leaves = $query->paginate(15)->withQueryString();

        return view('leaves.index', compact('leaves', 'filterEmployee'));
    }

    public function create(Request $request)
    {
        // Use employees list; we'll store the employee's user_id on the leaves table
        $employees = Employee::orderBy('employee_name')->get();

        return view('leaves.create', [
            'employees' => $employees,
            'selectedUserId' => $request->query('user_id'),
            'leaveTypes' => $this->getLeaveTypes(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'string', 'exists:employees,user_id'],
            'leave_date' => ['required', 'date'],
            'leave_type' => ['required', 'string', 'max:50'],
            'leave_description' => ['nullable', 'string', 'max:255'],
        ]);

        // Default approval_status to 0 (pending)
        $validated['approval_status'] = 0;

        Leave::create($validated);

        return redirect()->route('leaves.index', ['user_id' => $validated['user_id'] ?? null])->with('success', 'Leave created successfully.');
    }

    public function edit(Leave $leave)
    {
        if ($leave->approval_status !== 0) {
            return redirect()->route('leaves.index', ['user_id' => $leave->user_id])->with('error', 'Only pending leaves can be edited.');
        }

        $employees = Employee::orderBy('employee_name')->get();

        return view('leaves.edit', [
            'leave' => $leave,
            'employees' => $employees,
            'leaveTypes' => $this->getLeaveTypes(),
        ]);
    }

    public function update(Request $request, Leave $leave)
    {
        if ($leave->approval_status !== 0) {
            return redirect()->route('leaves.index', ['user_id' => $leave->user_id])->with('error', 'Approved leaves cannot be edited.');
        }

        $validated = $request->validate([
            'user_id' => ['nullable', 'string', 'exists:employees,user_id'],
            'leave_date' => ['required', 'date'],
            'leave_type' => ['required', 'string', 'max:50'],
            'leave_description' => ['nullable', 'string', 'max:255'],
        ]);

        $leave->update($validated);

        return redirect()->route('leaves.index', ['user_id' => $validated['user_id'] ?? $leave->user_id])->with('success', 'Leave updated successfully.');
    }

    public function approval(Request $request)
    {
        $userId = $request->query('user_id');
        $month = intval($request->query('month', Carbon::now()->month));
        $year = intval($request->query('year', Carbon::now()->year));

        $employees = Employee::orderBy('employee_name')->get();
        $filterEmployee = null;

        if ($userId) {
            $filterEmployee = Employee::where('user_id', $userId)->first();
        }

        $absentCount = DB::table('daily_attendance_step3')
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->whereYear('attendance_date', $year)
            ->whereMonth('attendance_date', $month)
            ->where('attendance_status', 'Absent')
            ->count();

        $totalLeaves = Leave::when($userId, fn($q) => $q->where('user_id', $userId))
            ->whereYear('leave_date', $year)
            ->whereMonth('leave_date', $month)
            ->count();

        $pendingLeaves = Leave::when($userId, fn($q) => $q->where('user_id', $userId))
            ->where('approval_status', 0)
            ->orderBy('leave_date', 'desc')
            ->get();

        $approvedLeaves = Leave::when($userId, fn($q) => $q->where('user_id', $userId))
            ->where('approval_status', 1)
            ->orderBy('leave_date', 'desc')
            ->get();

        return view('leaves.approval', compact('employees', 'filterEmployee', 'userId', 'month', 'year', 'absentCount', 'totalLeaves', 'pendingLeaves', 'approvedLeaves'));
    }

    public function approve(Leave $leave)
    {
        if ($leave->approval_status !== 0) {
            return redirect()->route('leaves.index', ['user_id' => $leave->user_id])->with('error', 'Leave is already approved.');
        }

        $leave->update(['approval_status' => 1]);

        return redirect()->route('leaves.approval', ['user_id' => $leave->user_id])->with('success', 'Leave approved successfully.');
    }
}
