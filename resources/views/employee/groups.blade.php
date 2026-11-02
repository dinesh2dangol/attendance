<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Groups</title>
    <style>
        body { font-family: sans-serif; background: #f8fafc; color: #111827; }
        .container { max-width: 1000px; margin: 3rem auto; padding: 2rem; background: white; border-radius: .75rem; box-shadow: 0 10px 30px rgba(15,23,42,.08); }
        .toolbar, .actions { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; }
        .toolbar { justify-content: space-between; margin-bottom: 1rem; }
        .button { display: inline-block; padding: .6rem .9rem; border-radius: .5rem; background: #111827; color: white; text-decoration: none; }
        .secondary { background: #e2e8f0; color: #111827; }
        .group { padding: 1rem 0; border-top: 1px solid #e5e7eb; }
        .group h2 { margin: 0 0 .5rem; }
        @media (max-width: 700px) { .container { margin: 1rem; padding: 1rem; } .toolbar { align-items: stretch; flex-direction: column; } }
    </style>
</head>
<body>
    <main class="container">
        <div class="toolbar">
            <div>
                <h1>My Groups</h1>
                <p>Groups assigned to you as supervisor.</p>
            </div>
            <div class="actions">
                <a class="button secondary" href="{{ route('employee.attendance.self') }}">My Attendance</a>
                <a class="button secondary" href="{{ route('employee.attendance.team') }}">Team Attendance</a>
                <a class="button secondary" href="{{ route('leaves.approval') }}">Leave Approvals</a>
                <a class="button secondary" href="{{ route('wfh-requests.index') }}">WFH Requests</a>
            </div>
        </div>

        @forelse ($groups as $group)
            <section class="group">
                <h2>{{ $group->name }}</h2>
                <p>{{ $group->members->count() }} employee(s)</p>
                @if ($group->members->isNotEmpty())
                    <ul>
                        @foreach ($group->members as $member)
                            <li>
                                {{ $member->employee_name }}
                                @if ($member->user_id)
                                    — <a href="{{ route('employee.attendance', $member) }}">View attendance</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @empty
            <p>You are not currently assigned to any groups.</p>
        @endforelse
    </main>
</body>
</html>
