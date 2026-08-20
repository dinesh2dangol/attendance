<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaves</title>
    <style>
        body { font-family: sans-serif; background: #f8fafc; color: #111827; }
        .container { max-width: 900px; margin: 4rem auto; padding: 2rem; background: white; border-radius: 0.75rem; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); }
        .toolbar { display: flex; justify-content: space-between; gap: 1rem; align-items: center; margin-bottom: 1.5rem; }
        .button { display: inline-flex; padding: 0.6rem 0.9rem; background: #111827; color: white; text-decoration: none; border-radius: 0.5rem; border: none; cursor: pointer; }
        .button-secondary { background: #e2e8f0; color: #0f172a; }
        .button-secondary:hover { background: #cbd5e1; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
        th, td { padding: 0.75rem 0.85rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f3f4f6; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <div class="toolbar">
            <div>
                <h1>Leaves</h1>
                @if(isset($filterEmployee) && $filterEmployee)
                    <p>Showing leaves for {{ $filterEmployee->employee_name }}.</p>
                @else
                    <p>Manage employee leaves.</p>
                @endif
            </div>
            <div style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:center;">
                <a href="{{ route('dashboard') }}" class="button button-secondary">Back to Dashboard</a>
                <a href="{{ route('leaves.approval', ['user_id' => request('user_id')]) }}" class="button button-secondary">Approval</a>
                <a href="{{ route('leaves.create', ['user_id' => request('user_id')]) }}" class="button">Add Leave</a>
            </div>
        </div>

        @if(session('success'))
            <div style="margin-bottom:1rem;color:green">{{ session('success') }}</div>
        @endif

        @if($leaves->isNotEmpty())
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Leave ID</th>
                            <th>User</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Approval</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($leaves as $leave)
                            <tr>
                                <td>{{ $leave->leave_id }}</td>
                                <td>{{ $leave->employee?->employee_name ?? $leave->user_id }}</td>
                                <td>{{ optional($leave->leave_date)->format('Y-m-d') }}</td>
                                <td>{{ $leave->leave_type }}</td>
                                <td>{{ Str::limit($leave->leave_description, 80) }}</td>
                                <td>{{ $leave->approval_status }}</td>
                                <td>
                                    @if($leave->approval_status === 0)
                                        <a href="{{ route('leaves.edit', $leave) }}">Edit</a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="display:flex;justify-content:flex-end">{{ $leaves->links() }}</div>
        @else
            <p>No leaves recorded yet.</p>
        @endif
    </div>
</body>
</html>
