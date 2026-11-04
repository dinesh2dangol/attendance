<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Attendance Records</title>
    <style>
        body { font-family: sans-serif; background: #f8fafc; color: #111827; }
        .container { max-width: 1100px; margin: 3rem auto; padding: 2rem; background: white; border-radius: .75rem; box-shadow: 0 10px 30px rgba(15,23,42,.08); }
        .toolbar, .filters { display: flex; flex-wrap: wrap; align-items: end; gap: 1rem; }
        .toolbar { justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .filters { margin-bottom: 1.5rem; }
        .field { display: flex; flex-direction: column; gap: .4rem; }
        input, select { min-width: 12rem; padding: .65rem; border: 1px solid #cbd5e1; border-radius: .4rem; background: white; }
        .button { display: inline-block; padding: .65rem .9rem; border: 0; border-radius: .5rem; background: #111827; color: white; text-decoration: none; cursor: pointer; }
        .secondary { background: #e2e8f0; color: #111827; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: .7rem; border-bottom: 1px solid #e5e7eb; text-align: left; white-space: nowrap; }
        th { background: #f3f4f6; }
        .error { color: #b91c1c; }
        @media (max-width: 700px) { .container { margin: 1rem; padding: 1rem; } .toolbar { align-items: stretch; flex-direction: column; } }
    </style>
</head>
<body>
    <main class="container">
        <div class="toolbar">
            <div>
                <h1>Team Attendance Records</h1>
                <p>View daily attendance details for yourself or a team member.</p>
            </div>
            <div>
                <a class="button secondary" href="{{ route('employee.attendance.team') }}">Team Attendance</a>
                <a class="button secondary" href="{{ route('employee.attendance.self') }}">My Attendance</a>
                <a class="button secondary" href="{{ route('dashboard') }}">Dashboard</a>
            </div>
        </div>

        <form method="GET" action="{{ route('employee.attendance.team.records') }}" class="filters">
            <div class="field">
                <label for="employee-user-id">Employee</label>
                <select id="employee-user-id" name="employee_user_id" required>
                    <option value="">Select an employee</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->user_id }}" @selected($selectedEmployee?->user_id === $employee->user_id)>
                            {{ $employee->employee_name }}{{ $employee->user_id === auth()->user()->employee?->user_id ? ' (You)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="attendance-from">From</label>
                <input id="attendance-from" type="date" name="from" value="{{ $from }}" required>
            </div>
            <div class="field">
                <label for="attendance-to">To</label>
                <input id="attendance-to" type="date" name="to" value="{{ $to }}" required>
            </div>
            <button class="button" type="submit">Show Records</button>
        </form>

        @if ($errors->any())
            <ul class="error" role="alert">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        @if ($hasFilters && $selectedEmployee)
            <h2>{{ $selectedEmployee->employee_name }}: {{ $from }} to {{ $to }}</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Attendance</th>
                            <th>WFH</th>
                            <th>Holiday</th>
                            <th>Check In</th>
                            <th>Lunch Out</th>
                            <th>Lunch In</th>
                            <th>Lunch Time (minutes)</th>
                            <th>Check Out</th>
                            <th>Approval Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $rangeDate = \Illuminate\Support\Carbon::parse($from);
                            $rangeEnd = \Illuminate\Support\Carbon::parse($to);
                        @endphp
                        @while ($rangeDate->lte($rangeEnd))
                            @php
                                $date = $rangeDate->toDateString();
                                $attendance = $attendanceByDate->get($date);
                                $approved = $attendance->supervisor_punch_selection_applied ?? false;
                                $lunchOut = $attendance
                                    ? ($approved ? $attendance->lunch_out : ($attendance->lunch_out ?? $attendance->punch_2 ?? null))
                                    : null;
                                $lunchIn = $attendance
                                    ? ($approved ? $attendance->lunch_in : ($attendance->lunch_in ?? $attendance->punch_3 ?? null))
                                    : null;
                                $lunchMinutes = filled($lunchOut) && filled($lunchIn)
                                    ? intdiv((int) abs(\Illuminate\Support\Carbon::parse($lunchOut)->diffInSeconds(\Illuminate\Support\Carbon::parse($lunchIn))), 60)
                                    : null;
                                $selection = $punchSelectionsByDate->get($date);
                                $isApprovedWfh = $approvedWfhDates->has($date);
                                $isPendingWfh = $pendingWfhDates->has($date);
                                $holiday = $holidaysByDate->get($date);
                            @endphp
                            <tr>
                                <td>{{ $date }}</td>
                                <td>{{ $attendance->attendance_status ?? 'No entry' }}</td>
                                <td>{{ $isPendingWfh ? 'Pending' : ($isApprovedWfh ? 'Approved' : '-') }}</td>
                                <td>{{ $holiday?->holiday_name ?? '-' }}</td>
                                <td>{{ $attendance?->first_punch ?? '-' }}</td>
                                <td>{{ $lunchOut ?? '-' }}</td>
                                <td>{{ $lunchIn ?? '-' }}</td>
                                <td>{{ $lunchMinutes ?? '-' }}</td>
                                <td>{{ $attendance?->last_punch ?? '-' }}</td>
                                <td>{{ $selection?->remarks ?? '-' }}</td>
                            </tr>
                            @php $rangeDate->addDay(); @endphp
                        @endwhile
                    </tbody>
                </table>
            </div>
        @endif
    </main>
</body>
</html>
