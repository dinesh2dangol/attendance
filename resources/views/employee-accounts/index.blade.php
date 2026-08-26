<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Login Management</title>
    <style>
        body { font-family: sans-serif; background: #f8fafc; color: #111827; }
        .container { max-width: 1100px; margin: 4rem auto; padding: 2rem; background: white; border-radius: 0.75rem; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); }
        .toolbar { display:flex; justify-content:space-between; align-items:center; gap:1rem; margin-bottom:1.5rem; }
        .button { display:inline-flex; padding:0.6rem 0.9rem; background:#111827; color:white; text-decoration:none; border-radius:0.5rem; border:0; cursor:pointer; }
        .button-secondary { background:#f3f4f6; color:#111827; }
        .message { margin-bottom:1rem; padding:1rem; border-radius:0.5rem; }
        .success { background:#dcfce7; color:#166534; }
        .error { background:#fee2e2; color:#991b1b; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:0.75rem 0.85rem; text-align:left; border-bottom:1px solid #e5e7eb; vertical-align:top; }
        th { background:#f3f4f6; }
        input { width:100%; box-sizing:border-box; padding:0.6rem; border:1px solid #d1d5db; border-radius:0.5rem; }
        form { display:flex; gap:0.5rem; min-width:300px; }
        @media (max-width: 760px) { .container { margin:1rem; padding:1rem; } .toolbar { align-items:flex-start; flex-direction:column; } table { min-width:850px; } .table-wrap { overflow-x:auto; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="toolbar">
            <div>
                <h1>Employee Login Management</h1>
                <p>Generate employee login credentials by department.</p>
            </div>
            <a class="button button-secondary" href="{{ route('dashboard') }}">Back to Dashboard</a>
        </div>

        @if (session('credentials'))
            <div class="message success">
                <strong>Temporary credentials generated for {{ session('credentials.employee') }}</strong><br>
                Email: {{ session('credentials.email') }}<br>
                Temporary password: <strong>{{ session('credentials.password') }}</strong>
            </div>
        @endif

        @if ($errors->any())
            <div class="message error">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Login Email</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr>
                            <td>{{ $employee->employee_name }}</td>
                            <td>{{ $employee->department?->department_name ?? 'N/A' }}</td>
                            <td>{{ $employee->account?->email ?? 'Not configured' }}</td>
                            <td>
                                <form method="POST" action="{{ route('employee-accounts.store', $employee) }}">
                                    @csrf
                                    <input type="email" name="email" value="{{ old('email', $employee->account?->email) }}" placeholder="employee@example.com" required>
                                    <button class="button" type="submit">Generate Password</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No employees found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
