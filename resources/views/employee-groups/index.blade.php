<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Groups</title>
    <style>
        body { font-family: sans-serif; background: #f8fafc; color: #111827; }
        .container { max-width: 1000px; margin: 3rem auto; padding: 2rem; background: white; border-radius: .75rem; box-shadow: 0 10px 30px rgba(15,23,42,.08); }
        .toolbar { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:1rem; }
        .actions { display:flex; gap:.5rem; }
        .button { display:inline-block; padding:.6rem .9rem; border:0; border-radius:.5rem; color:white; background:#111827; text-decoration:none; cursor:pointer; }
        .secondary { background:#e2e8f0; color:#111827; }
        .message { padding:.75rem; margin:1rem 0; background:#dcfce7; color:#166534; border-radius:.5rem; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; margin-top:1rem; }
        th,td { text-align:left; padding:.75rem; border-bottom:1px solid #e5e7eb; vertical-align:top; }
        @media(max-width:700px) { .container { margin:1rem; padding:1rem; } .toolbar { align-items:stretch; flex-direction:column; } }
    </style>
</head>
<body>
    <main class="container">
        <div class="toolbar">
            <div>
                <h1>Employee Groups</h1>
                <p>Assign a supervisor and regular employees to each group.</p>
            </div>
            <div class="actions">
                <a class="button secondary" href="{{ route('dashboard') }}">Dashboard</a>
                <a class="button" href="{{ route('employee-groups.create') }}">Create Group</a>
            </div>
        </div>

        @if (session('success'))
            <div class="message">{{ session('success') }}</div>
        @endif

        @if ($groups->isEmpty())
            <p>No employee groups created yet.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Group</th><th>Supervisor</th><th>Employees</th><th>Attendance</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($groups as $group)
                            <tr>
                                <td>{{ $group->name }}</td>
                                <td>{{ $group->supervisor?->employee_name ?? 'Not assigned' }}</td>
                                <td>{{ $group->members->pluck('employee_name')->join(', ') ?: 'No members' }}</td>
                                <td><a href="{{ route('employee-groups.attendance', $group) }}">View Attendance</a></td>
                                <td><a href="{{ route('employee-groups.edit', $group) }}">Edit</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="display:flex;justify-content:flex-end;margin-top:1rem">{{ $groups->links() }}</div>
        @endif
    </main>
</body>
</html>
