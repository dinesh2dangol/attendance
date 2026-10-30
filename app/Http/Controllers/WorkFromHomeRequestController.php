<?php

namespace App\Http\Controllers;

use App\Models\WorkFromHomeRequest;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkFromHomeRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = WorkFromHomeRequest::with('employee')
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at');

        if ($request->user()->role?->slug === 'employee') {
            $employee = $request->user()->employee;
            abort_unless($employee, 403, 'Your account is not linked to an employee record.');
            $query->where('user_id', $employee->user_id);
        }

        $requests = $query->paginate(20)->withQueryString();

        return view('work-from-home.index', compact('requests'));
    }

    public function create(Request $request)
    {
        $role = $request->user()->role?->slug;
        abort_unless(in_array($role, ['employee', 'superadmin'], true), 403);

        if ($role === 'employee') {
            abort_unless($request->user()->employee, 403, 'Your account is not linked to an employee record.');

            return view('work-from-home.create');
        }

        $employees = Employee::orderBy('employee_name')->get();

        return view('work-from-home.create', [
            'employees' => $employees,
            'selectedUserId' => $request->query('user_id'),
        ]);
    }

    public function store(Request $request)
    {
        $role = $request->user()->role?->slug;
        $isEmployee = $role === 'employee';
        $isSuperadmin = $role === 'superadmin';
        abort_unless($isEmployee || $isSuperadmin, 403);

        $validated = $request->validate([
            'user_id' => [$isSuperadmin ? 'required' : 'prohibited', 'string', 'exists:employees,user_id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'start_date.after_or_equal' => 'The start date must be today or later.',
            'end_date.after_or_equal' => 'The end date must be on or after the start date.',
        ]);
        $employee = $isEmployee
            ? $request->user()->employee
            : Employee::where('user_id', $validated['user_id'])->firstOrFail();
        abort_unless($employee, 403, 'Your account is not linked to an employee record.');

        $hasOverlappingRequest = WorkFromHomeRequest::query()
            ->where('user_id', $employee->user_id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $validated['end_date'])
            ->whereDate('end_date', '>=', $validated['start_date'])
            ->exists();

        if ($hasOverlappingRequest) {
            throw ValidationException::withMessages([
                'start_date' => 'A pending or approved WFH request already covers part of these dates.',
            ]);
        }

        $hasOverlappingLeave = DB::table('leaves')
            ->where('user_id', $employee->user_id)
            ->whereBetween('leave_date', [$validated['start_date'], $validated['end_date']])
            ->exists();

        if ($hasOverlappingLeave) {
            throw ValidationException::withMessages([
                'start_date' => 'The requested dates overlap with a leave request.',
            ]);
        }

        WorkFromHomeRequest::create([
            ...$validated,
            'user_id' => $employee->user_id,
        ]);

        $destination = $isSuperadmin ? 'wfh-requests.index' : 'employee.wfh.index';

        return redirect()->route($destination)->with('success', 'WFH request submitted for approval.');
    }

    public function approve(WorkFromHomeRequest $workFromHomeRequest, Request $request)
    {
        return $this->review($workFromHomeRequest, $request, 'approved');
    }

    public function reject(WorkFromHomeRequest $workFromHomeRequest, Request $request)
    {
        return $this->review($workFromHomeRequest, $request, 'rejected');
    }

    private function review(WorkFromHomeRequest $wfhRequest, Request $request, string $status)
    {
        abort_unless(in_array($request->user()->role?->slug, ['admin', 'superadmin'], true), 403);

        if ($wfhRequest->status !== 'pending') {
            return redirect()->route('wfh-requests.index')->with('error', 'This WFH request has already been reviewed.');
        }

        $wfhRequest->update([
            'status' => $status,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return redirect()->route('wfh-requests.index')->with(
            'success',
            $status === 'approved' ? 'WFH request approved.' : 'WFH request rejected.'
        );
    }
}
