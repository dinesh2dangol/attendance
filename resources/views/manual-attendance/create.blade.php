<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Manual Attendance</title>
    <style>
        body { font-family: sans-serif; background: #f8fafc; color: #111827; }
        .container { max-width: 700px; margin: 4rem auto; padding: 2rem; background: white; border-radius: 0.75rem; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); }
        .field { display:flex; flex-direction:column; gap:0.5rem; margin-bottom:1rem }
        input, select, textarea { padding:0.6rem; border:1px solid #d1d5db; border-radius:0.5rem; }
        .button { padding:0.6rem 0.9rem; background:#111827; color:white; border-radius:0.5rem; border:none; cursor:pointer }
    </style>
</head>
<body>
    <div class="container">
        <h1>Add Manual Attendance</h1>

        @if ($errors->any())
            <div style="color:red;margin-bottom:1rem">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('manual-attendance.store') }}">
            @csrf

            <div class="field">
                <label for="user_id">Employee</label>
                <select name="user_id" id="user_id" required>
                    <option value="">-- Select employee --</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->user_id }}" {{ (string)($selectedUserId ?? old('user_id')) === (string)$emp->user_id ? 'selected' : '' }}>
                            {{ $emp->employee_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="timestamp">Timestamp</label>
                <input id="timestamp" name="timestamp" type="datetime-local" value="{{ old('timestamp', $selectedTimestamp ?? '') }}" required />
            </div>

            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status" required>
                    <option value="">-- Select status --</option>
                    <option value="1" {{ old('status') === '1' ? 'selected' : '' }}>Present</option>
                    <option value="0" {{ old('status') === '0' ? 'selected' : '' }}>Absent</option>
                </select>
            </div>

            <div class="field">
                <label for="punch_type">Punch Type</label>
                <select id="punch_type" name="punch_type" required>
                    <option value="">-- Select punch type --</option>
                    <option value="IN" {{ old('punch_type') === 'IN' ? 'selected' : '' }}>IN</option>
                    <option value="OUT" {{ old('punch_type') === 'OUT' ? 'selected' : '' }}>OUT</option>
                    <option value="LUNCH_IN" {{ old('punch_type') === 'LUNCH_IN' ? 'selected' : '' }}>LUNCH_IN</option>
                    <option value="LUNCH_OUT" {{ old('punch_type') === 'LUNCH_OUT' ? 'selected' : '' }}>LUNCH_OUT</option>
                </select>
            </div>

            <div class="field">
                <label for="remarks">Remarks</label>
                <textarea id="remarks" name="remarks" rows="4">{{ old('remarks') }}</textarea>
            </div>

            <div style="display:flex;gap:0.5rem">
                <button class="button" type="submit">Save</button>
                <a href="{{ route('dashboard') }}" style="align-self:center;">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
