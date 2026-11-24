<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Meeting Rooms</title>
    <style>
        body { font-family: sans-serif; background: #f8fafc; color: #111827; }
        .container { max-width: 900px; margin: 3rem auto; padding: 2rem; background: white; border-radius: .75rem; box-shadow: 0 10px 30px rgba(15,23,42,.08); }
        .toolbar, .actions { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .75rem; align-items: center; }
        .button { display: inline-block; padding: .65rem .9rem; border-radius: .5rem; background: #111827; color: white; text-decoration: none; border: 0; cursor: pointer; }
        .secondary { background: #e2e8f0; color: #111827; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: .75rem; border-bottom: 1px solid #e5e7eb; text-align: left; }
        .notice { padding: .75rem; background: #dcfce7; border-radius: .5rem; }
        @media (max-width: 640px) { .container { margin: 1rem; padding: 1rem; } .toolbar { align-items: stretch; flex-direction: column; } }
    </style>
</head>
<body>
    <main class="container">
        <div class="toolbar">
            <div>
                <h1>Manage Meeting Rooms</h1>
                <p>Create rooms or deactivate them to stop future bookings.</p>
            </div>
            <div class="actions">
                <a class="button secondary" href="{{ route('meeting-rooms.index') }}">Booking Calendar</a>
                <a class="button secondary" href="{{ route('dashboard') }}">Dashboard</a>
                <a class="button" href="{{ route('meeting-rooms.create') }}">Add Room</a>
            </div>
        </div>

        @if (session('success'))
            <p class="notice" role="status">{{ session('success') }}</p>
        @endif

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Room</th><th>Location</th><th>Capacity</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($rooms as $room)
                        <tr>
                            <td>{{ $room->name }}</td>
                            <td>{{ $room->location ?: '—' }}</td>
                            <td>{{ $room->capacity ?: '—' }}</td>
                            <td>{{ $room->is_active ? 'Active' : 'Inactive' }}</td>
                            <td><a href="{{ route('meeting-rooms.edit', $room) }}">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No meeting rooms have been set up.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
