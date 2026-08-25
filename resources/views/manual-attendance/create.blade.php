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
        .hidden { display: none; }
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
                <label for="attendance_date">Date</label>
                <input id="attendance_date" name="attendance_date" type="date" value="{{ old('attendance_date', \Carbon\Carbon::parse($selectedTimestamp ?? now())->format('Y-m-d')) }}" required />
            </div>

            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status" required>
                    <option value="">-- Select status --</option>
                    <option value="1" {{ old('status') === '1' ? 'selected' : '' }}>Present</option>
                    <option value="0" {{ old('status') === '0' ? 'selected' : '' }}>Absent</option>
                </select>
            </div>

            <div id="present-fields" class="hidden">
                @php
                    $defaultPunchTimes = [
                        'IN' => '09:00',
                        'OUT' => '17:00',
                        'LUNCH_IN' => '',
                        'LUNCH_OUT' => '',
                    ];
                @endphp
                @foreach (['IN' => 'In', 'OUT' => 'Out', 'LUNCH_IN' => 'Lunch In', 'LUNCH_OUT' => 'Lunch Out'] as $punchType => $label)
                    <div class="field">
                        <label for="punch_{{ $punchType }}">{{ $label }} Time</label>
                        <input id="punch_{{ $punchType }}" name="punch_{{ $punchType }}" type="time" value="{{ old('punch_' . $punchType, $defaultPunchTimes[$punchType] ?? '') }}" />
                    </div>
                @endforeach
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

    <script>
        const statusSelect = document.getElementById('status');
        const presentFields = document.getElementById('present-fields');

        function togglePunchFields() {
            const isPresent = statusSelect.value === '1';
            presentFields.classList.toggle('hidden', !isPresent);
        }

        statusSelect.addEventListener('change', togglePunchFields);
        togglePunchFields();
    </script>
</body>
</html>
