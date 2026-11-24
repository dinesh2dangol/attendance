<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $meetingRoom->exists ? 'Edit' : 'Add' }} Meeting Room</title>
    <style>
        body { font-family: sans-serif; background: #f8fafc; color: #111827; }
        .container { max-width: 640px; margin: 3rem auto; padding: 2rem; background: white; border-radius: .75rem; box-shadow: 0 10px 30px rgba(15,23,42,.08); }
        .field { display: grid; gap: .4rem; margin-bottom: 1rem; }
        input { padding: .7rem; border: 1px solid #cbd5e1; border-radius: .4rem; }
        .button { display: inline-block; padding: .65rem .9rem; border-radius: .5rem; background: #111827; color: white; text-decoration: none; border: 0; cursor: pointer; }
        .secondary { background: #e2e8f0; color: #111827; }
        .error { color: #b91c1c; }
        @media (max-width: 640px) { .container { margin: 1rem; padding: 1rem; } }
    </style>
</head>
<body>
    <main class="container">
        <h1>{{ $meetingRoom->exists ? 'Edit' : 'Add' }} Meeting Room</h1>
        <form method="POST" action="{{ $meetingRoom->exists ? route('meeting-rooms.update', $meetingRoom) : route('meeting-rooms.store') }}">
            @csrf
            @if ($meetingRoom->exists)
                @method('PUT')
            @endif
            <label class="field" for="name">Room name
                <input id="name" name="name" value="{{ old('name', $meetingRoom->name) }}" maxlength="100" required>
                @error('name') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label class="field" for="location">Location
                <input id="location" name="location" value="{{ old('location', $meetingRoom->location) }}" maxlength="150">
                @error('location') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label class="field" for="capacity">Capacity
                <input id="capacity" type="number" name="capacity" value="{{ old('capacity', $meetingRoom->capacity) }}" min="1" max="65535">
                @error('capacity') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label class="field">
                <span><input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $meetingRoom->exists ? $meetingRoom->is_active : true))>
                Room is active and available to book</span>
                @error('is_active') <span class="error">{{ $message }}</span> @enderror
            </label>
            <button class="button" type="submit">Save Room</button>
            <a class="button secondary" href="{{ route('meeting-rooms.manage') }}">Cancel</a>
        </form>
    </main>
</body>
</html>
