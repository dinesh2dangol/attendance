<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Attendance</title>
    <style>
        body { font-family: sans-serif; background: #f8fafc; color: #111827; }
        .container { max-width: 900px; margin: 3rem auto; padding: 2rem; background: white; border-radius: .75rem; box-shadow: 0 10px 30px rgba(15,23,42,.08); }
        .toolbar { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem; align-items: center; }
        .button { display: inline-block; padding: .6rem .9rem; border-radius: .5rem; background: #111827; color: white; text-decoration: none; }
        .secondary { background: #e2e8f0; color: #111827; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: .75rem; border-bottom: 1px solid #e5e7eb; text-align: left; }
        td select { display: block; min-width: 10rem; max-width: 15rem; margin-top: .5rem; padding: .4rem; }
        @media (max-width: 700px) { .container { margin: 1rem; padding: 1rem; } .toolbar { align-items: stretch; flex-direction: column; } }
    </style>
</head>
<body>
    <main class="container">
        <div class="toolbar">
            <div>
                <h1>Team Attendance</h1>
                <p>Review attendance punches for employees assigned to your group or groups.</p>
            </div>
            <div>
                <a class="button secondary" href="{{ route('employee.groups') }}">My Groups</a>
                <a class="button secondary" href="{{ route('employee.attendance.self') }}">My Attendance</a>
                <a class="button secondary" href="{{ route('leaves.approval') }}">Leave Approvals</a>
                <a class="button secondary" href="{{ route('wfh-requests.index') }}">WFH Requests</a>
                <a class="button secondary" href="{{ route('dashboard') }}">Dashboard</a>
            </div>
        </div>

        <form method="GET" action="{{ route('employee.attendance.team') }}">
            <label for="attendance-date">Date</label>
            <input id="attendance-date" type="date" name="date" value="{{ $attendanceDate->format('Y-m-d') }}" required>
            <button class="button" type="submit">Show Attendance</button>
        </form>

        @if (session('success'))
            <p role="status">{{ session('success') }}</p>
        @endif
        @if ($errors->any())
            <ul role="alert">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        @if ($members->isEmpty())
            <p>No employees are assigned to your group yet.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Attendance ({{ $attendanceDate->format('Y-m-d') }})</th>
                            <th>Check In</th>
                            <th>Lunch Out</th>
                            <th>Lunch In</th>
                            <th>Lunch Time</th>
                            <th>Check Out</th>
                            <th>History</th>
                            <th>Punch Approval</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($supervisor)
                            @php
                                $supervisorAttendance = $attendanceByUser->get($supervisor->user_id);
                                $supervisorSelection = $punchSelectionsByUser->get($supervisor->user_id);
                                $hasSupervisorApproval = $supervisorAttendance?->supervisor_punch_selection_applied ?? false;
                                $supervisorLunchOut = $hasSupervisorApproval
                                    ? ($supervisorAttendance->lunch_out ?? '-')
                                    : ($supervisorAttendance?->lunch_out ?? $supervisorAttendance?->punch_2 ?? '-');
                                $supervisorLunchIn = $hasSupervisorApproval
                                    ? ($supervisorAttendance->lunch_in ?? '-')
                                    : ($supervisorAttendance?->lunch_in ?? $supervisorAttendance?->punch_3 ?? '-');
                                $supervisorLunchMinutes = filled($supervisorLunchOut) && $supervisorLunchOut !== '-' && filled($supervisorLunchIn) && $supervisorLunchIn !== '-'
                                    ? intdiv((int) abs(\Illuminate\Support\Carbon::parse($supervisorLunchOut)->diffInSeconds(\Illuminate\Support\Carbon::parse($supervisorLunchIn))), 60)
                                    : null;
                            @endphp
                            <tr>
                                <td>{{ $supervisor->employee_name }} (You)</td>
                                <td>{{ $supervisorAttendance?->attendance_status ?? 'Not recorded' }}</td>
                                <td>{{ $supervisorAttendance?->first_punch ?? '-' }}</td>
                                <td>{{ $supervisorLunchOut }}</td>
                                <td>{{ $supervisorLunchIn }}</td>
                                <td>{{ $supervisorLunchMinutes !== null ? $supervisorLunchMinutes.' minutes' : '-' }}</td>
                                <td>{{ $supervisorAttendance?->last_punch ?? '-' }}</td>
                                <td>
                                    <a href="{{ route('employee.attendance.self') }}">View Attendance</a>
                                    @if ($supervisorSelection)
                                        <small>Approved by {{ $supervisorSelection->approved_by_name ?? 'supervisor' }}</small>
                                        @if (filled($supervisorSelection->remarks))
                                            <div><strong>Remarks:</strong> {{ $supervisorSelection->remarks }}</div>
                                        @endif
                                    @endif
                                </td>
                                <td>-</td>
                            </tr>
                        @endif
                        @foreach ($members as $member)
                            @php
                                $attendance = $attendanceByUser->get($member->user_id);
                                $selection = $punchSelectionsByUser->get($member->user_id);
                                $hasApprovedPunchSelection = $attendance?->supervisor_punch_selection_applied ?? false;
                                $lunchOut = $hasApprovedPunchSelection
                                    ? ($attendance->lunch_out ?? '-')
                                    : ($attendance?->lunch_out ?? $attendance?->punch_2 ?? '-');
                                $lunchIn = $hasApprovedPunchSelection
                                    ? ($attendance->lunch_in ?? '-')
                                    : ($attendance?->lunch_in ?? $attendance?->punch_3 ?? '-');
                                $lunchMinutes = filled($lunchOut) && $lunchOut !== '-' && filled($lunchIn) && $lunchIn !== '-'
                                    ? intdiv((int) abs(\Illuminate\Support\Carbon::parse($lunchOut)->diffInSeconds(\Illuminate\Support\Carbon::parse($lunchIn))), 60)
                                    : null;
                            @endphp
                            <tr>
                                <td>{{ $member->employee_name }}</td>
                                <td>{{ $attendance?->attendance_status ?? 'Not recorded' }}</td>
                                @if ($attendance)
                                    <td>
                                        {{ $attendance->first_punch ?? '-' }}
                                    </td>
                                    <td>
                                        {{ $lunchOut }}
                                    </td>
                                    <td>
                                        {{ $lunchIn }}
                                    </td>
                                    <td>{{ $lunchMinutes !== null ? $lunchMinutes.' minutes' : '-' }}</td>
                                    <td>
                                        {{ $attendance->last_punch ?? '-' }}
                                    </td>
                                @else
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                @endif
                                <td><a href="{{ route('employee.attendance', $member) }}">View Attendance</a></td>
                                <td>
                                    @if ($attendance)
                                        <a class="button" href="{{ route('employee.attendance.team.punch-selection.edit', ['employee' => $member, 'date' => $attendanceDate->format('Y-m-d')]) }}">
                                            {{ $selection ? 'Edit Approval' : 'Review Punches' }}
                                        </a>
                                        @if ($selection)
                                            <small>Approved by {{ $selection->approved_by_name ?? 'supervisor' }}</small>
                                            @if (filled($selection->remarks))
                                                <div><strong>Remarks:</strong> {{ $selection->remarks }}</div>
                                            @endif
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </main>
</body>
</html>
