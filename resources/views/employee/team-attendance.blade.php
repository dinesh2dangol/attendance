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
        @media (max-width: 700px) { .container { margin: 1rem; padding: 1rem; } .toolbar { align-items: stretch; flex-direction: column; } }
    </style>
</head>
<body>
    <main class="container">
        <div class="toolbar">
            <div>
                <h1>Team Attendance</h1>
                <p>View attendance for employees assigned to your group or groups.</p>
            </div>
            <div>
                <a class="button secondary" href="{{ route('employee.groups') }}">My Groups</a>
                <a class="button secondary" href="{{ route('employee.attendance.self') }}">My Attendance</a>
                <a class="button secondary" href="{{ route('leaves.approval') }}">Leave Approvals</a>
                <a class="button secondary" href="{{ route('wfh-requests.index') }}">WFH Requests</a>
                <a class="button secondary" href="{{ route('dashboard') }}">Dashboard</a>
            </div>
        </div>

        @if ($members->isEmpty())
            <p>No employees are assigned to your group yet.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Employee</th><th>Attendance</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($members as $member)
                            <tr>
                                <td>{{ $member->employee_name }}</td>
                                <td><a href="{{ route('employee.attendance', $member) }}">View Attendance</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </main>
</body>
</html>
