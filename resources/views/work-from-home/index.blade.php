<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work From Home Requests</title>
    <style>
        body { font-family: sans-serif; background: #f8fafc; color: #111827; }
        .container { max-width: 1100px; margin: 3rem auto; padding: 2rem; background: white; border-radius: 0.75rem; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); }
        .toolbar { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem; align-items: center; margin-bottom: 1rem; }
        .actions { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .button { display: inline-block; padding: 0.6rem 0.9rem; background: #111827; color: white; border: 0; border-radius: 0.5rem; text-decoration: none; cursor: pointer; }
        .button-secondary { background: #e2e8f0; color: #111827; }
        .button-danger { background: #b91c1c; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; }
        .message { margin: 1rem 0; padding: 0.75rem; border-radius: 0.5rem; background: #dcfce7; color: #166534; }
        .message.error { background: #fee2e2; color: #991b1b; }
        @media (max-width: 700px) {
            .container { margin: 1rem; padding: 1rem; }
            .toolbar { align-items: stretch; flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="toolbar">
            <div>
                <h1>Work From Home Requests</h1>
                <p>{{ $isOwnRequestList ? 'Track your requests and their approval status.' : 'Review employee WFH requests.' }}</p>
            </div>
            <div class="actions">
                @if ($isOwnRequestList)
                    <a class="button button-secondary" href="{{ route('employee.attendance.self') }}">My Attendance</a>
                    <a class="button" href="{{ route('employee.wfh.create') }}">Request WFH</a>
                @else
                    <a class="button button-secondary" href="{{ route('dashboard') }}">Dashboard</a>
                    @if (auth()->user()->role?->slug === 'supervisor')
                        <a class="button button-secondary" href="{{ route('employee.wfh.index') }}">My WFH Requests</a>
                    @endif
                    @if (auth()->user()->role?->slug === 'superadmin')
                        <a class="button" href="{{ route('wfh-requests.create') }}">Apply for Employee</a>
                    @endif
                @endif
            </div>
        </div>

        @if (session('success'))
            <div class="message">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="message error">{{ session('error') }}</div>
        @endif

        @if ($requests->isEmpty())
            <p>No WFH requests found.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            @if (! $isOwnRequestList)
                                <th>Employee</th>
                            @endif
                            <th>Dates</th>
                            <th>Reason</th>
                            <th>Status</th>
                            @if (! $isOwnRequestList)
                                <th>Action</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $wfhRequest)
                            <tr>
                                @if (! $isOwnRequestList)
                                    <td>{{ $wfhRequest->employee?->employee_name ?? $wfhRequest->user_id }}</td>
                                @endif
                                <td>{{ $wfhRequest->start_date->format('Y-m-d') }} - {{ $wfhRequest->end_date->format('Y-m-d') }}</td>
                                <td>{{ $wfhRequest->reason }}</td>
                                <td>{{ ucfirst($wfhRequest->status) }}</td>
                                @if (! $isOwnRequestList)
                                    <td>
                                        @if ($wfhRequest->status === 'pending' && (in_array(auth()->user()->role?->slug, ['admin', 'superadmin'], true) || in_array((string) $wfhRequest->user_id, $reviewableUserIds ?? [], true)))
                                            <div class="actions">
                                                <form method="POST" action="{{ route('wfh-requests.approve', $wfhRequest) }}">
                                                    @csrf
                                                    <button class="button" type="submit">Approve</button>
                                                </form>
                                                <form method="POST" action="{{ route('wfh-requests.reject', $wfhRequest) }}">
                                                    @csrf
                                                    <button class="button button-danger" type="submit">Reject</button>
                                                </form>
                                            </div>
                                        @else
                                            {{ $wfhRequest->reviewed_at?->format('Y-m-d H:i') ?? '-' }}
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 1rem; display:flex; justify-content:flex-end">{{ $requests->links() }}</div>
        @endif
    </div>
</body>
</html>
