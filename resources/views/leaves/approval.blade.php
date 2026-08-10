<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Approval</title>
    <style>
        body { font-family: Inter, system-ui, sans-serif; background: #f8fafc; color: #0f172a; }
        .container { max-width: 1000px; margin: 3rem auto; padding: 2rem; background: white; border-radius: 1rem; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12); }
        .toolbar { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem; align-items: center; margin-bottom: 1.5rem; }
        .button { display: inline-flex; align-items: center; justify-content: center; padding: 0.8rem 1.1rem; border-radius: 0.75rem; border: none; cursor: pointer; font-weight: 600; color: white; background: #2563eb; text-decoration: none; }
        .button:hover { background: #1d4ed8; }
        .button-secondary { background: #e2e8f0; color: #0f172a; }
        .button-secondary:hover { background: #cbd5e1; }
        .grid { display: grid; gap: 1.25rem; grid-template-columns: 1fr 1fr; margin-bottom: 1.5rem; }
        .card { padding: 1.25rem; border: 1px solid #e2e8f0; border-radius: 1rem; background: #ffffff; }
        .card h2 { margin: 0 0 0.75rem; font-size: 1rem; color: #0f172a; }
        .badge { display: inline-flex; align-items: center; justify-content: center; padding: 0.45rem 0.75rem; border-radius: 9999px; font-size: 0.85rem; font-weight: 700; color: white; }
        .badge-pending { background: #f59e0b; }
        .badge-approved { background: #16a34a; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.9rem 1rem; border-bottom: 1px solid #e2e8f0; text-align: left; }
        th { background: #f8fafc; font-weight: 700; }
        td:last-child { white-space: nowrap; }
        .form-row { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .form-row label { display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.95rem; color: #475569; }
        .form-row select { padding: 0.75rem 0.85rem; border: 1px solid #cbd5e1; border-radius: 0.75rem; background: #f8fafc; }
        .message { margin-bottom: 1rem; padding: 1rem; border-radius: 0.75rem; }
        .message.success { background: #d1fae5; color: #065f46; }
        .message.error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="toolbar">
            <div>
                <h1>Leave Approval</h1>
                <p>Review pending leaves and see totals for absent days and leaves.</p>
            </div>
            <a class="button button-secondary" href="{{ route('dashboard') }}">Back to Dashboard</a>
        </div>

        @if(session('success'))
            <div class="message success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="message error">{{ session('error') }}</div>
        @endif

        <form method="GET" action="{{ route('leaves.approval') }}" class="form-row">
            <label>
                Employee
                <select name="user_id" onchange="this.form.submit()">
                    <option value="">All employees</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->user_id }}" {{ (string)$userId === (string)$emp->user_id ? 'selected' : '' }}>{{ $emp->employee_name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Month
                <select name="month" onchange="this.form.submit()">
                    @foreach(range(1, 12) as $num)
                        <option value="{{ $num }}" {{ $month === $num ? 'selected' : '' }}>{{ Date::createFromDate($year, $num, 1)->format('F') }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Year
                <select name="year" onchange="this.form.submit()">
                    @foreach(range(date('Y') - 1, date('Y') + 1) as $yearOption)
                        <option value="{{ $yearOption }}" {{ $year === $yearOption ? 'selected' : '' }}>{{ $yearOption }}</option>
                    @endforeach
                </select>
            </label>
        </form>

        <div class="grid">
            <div class="card">
                <h2>Total Absent</h2>
                <p style="font-size:2rem;font-weight:700;margin:0">{{ $absentCount }}</p>
            </div>
            <div class="card">
                <h2>Total Leaves</h2>
                <p style="font-size:2rem;font-weight:700;margin:0">{{ $totalLeaves }}</p>
            </div>
        </div>

        <div class="card" style="margin-bottom:1.5rem;">
            <h2>Pending Leaves</h2>
            @if($pendingLeaves->isEmpty())
                <p>No pending leaves found.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pendingLeaves as $leave)
                            <tr>
                                <td>{{ $leave->leave_id }}</td>
                                <td>{{ optional($leave->leave_date)->format('Y-m-d') }}</td>
                                <td>{{ $leave->leave_type }}</td>
                                <td>{{ Str::limit($leave->leave_description, 100) }}</td>
                                <td>
                                    <form method="POST" action="{{ route('leaves.approve', $leave) }}" style="display:inline">
                                        @csrf
                                        <button type="submit" class="button">Approve</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="card">
            <h2>Approved Leaves</h2>
            @if($approvedLeaves->isEmpty())
                <p>No approved leaves found.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($approvedLeaves as $leave)
                            <tr>
                                <td>{{ $leave->leave_id }}</td>
                                <td>{{ optional($leave->leave_date)->format('Y-m-d') }}</td>
                                <td>{{ $leave->leave_type }}</td>
                                <td>{{ Str::limit($leave->leave_description, 100) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</body>
</html>
