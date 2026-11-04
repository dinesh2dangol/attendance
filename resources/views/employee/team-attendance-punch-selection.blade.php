<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Attendance Punches</title>
    <style>
        body { font-family: sans-serif; background: #f8fafc; color: #111827; }
        .container { max-width: 760px; margin: 3rem auto; padding: 2rem; background: white; border-radius: .75rem; box-shadow: 0 10px 30px rgba(15,23,42,.08); }
        .toolbar { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem; align-items: center; }
        .button { display: inline-block; padding: .6rem .9rem; border-radius: .5rem; background: #111827; color: white; text-decoration: none; border: 0; cursor: pointer; }
        .secondary { background: #e2e8f0; color: #111827; }
        .field { display: flex; flex-direction: column; gap: .4rem; margin: 1rem 0; }
        select { max-width: 24rem; padding: .6rem; }
        textarea { max-width: 36rem; min-height: 7rem; padding: .6rem; }
        .raw-punches { line-height: 1.8; }
    </style>
</head>
<body>
    <main class="container">
        <div class="toolbar">
            <div>
                <h1>Review Attendance Punches</h1>
                <p>{{ $employee->employee_name }} — {{ $attendanceDate->format('Y-m-d') }}</p>
            </div>
            <a class="button secondary" href="{{ route('employee.attendance.team', ['date' => $attendanceDate->format('Y-m-d')]) }}">Back to Team Attendance</a>
        </div>

        @if ($errors->any())
            <ul role="alert">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <p class="raw-punches">
            <strong>Raw punches:</strong>
            @foreach (range(1, 5) as $punchNumber)
                @if (filled(data_get($attendance, 'punch_'.$punchNumber)))
                    Punch {{ $punchNumber }}: {{ data_get($attendance, 'punch_'.$punchNumber) }}@if ($punchNumber < 5); @endif
                @endif
            @endforeach
        </p>

        @if ($selection)
            <p role="status">Last approved by {{ $selection->approved_by_name ?? 'supervisor' }} on {{ $selection->updated_at }}.</p>
        @endif

        <form method="POST" action="{{ route('employee.attendance.team.punch-selection', $employee) }}">
            @csrf
            <input type="hidden" name="date" value="{{ $attendanceDate->format('Y-m-d') }}">

            @foreach ([
                'check_in_punch' => 'Check In',
                'lunch_out_punch' => 'Lunch Out',
                'lunch_in_punch' => 'Lunch In',
                'check_out_punch' => 'Check Out',
            ] as $field => $label)
                <div class="field">
                    <label for="{{ $field }}">{{ $label }}</label>
                    <select id="{{ $field }}" name="{{ $field }}">
                        <option value="">No punch</option>
                        @foreach (range(1, 5) as $punchNumber)
                            @php $punch = data_get($attendance, 'punch_'.$punchNumber); @endphp
                            @if (filled($punch))
                                <option value="{{ $punchNumber }}" @selected((int) old($field, $selectedPunchNumbers[$field] ?? 0) === $punchNumber)>
                                    Punch {{ $punchNumber }} — {{ $punch }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                </div>
            @endforeach

            <div class="field">
                <label for="remarks">Remarks (optional)</label>
                <textarea id="remarks" name="remarks" maxlength="2000">{{ old('remarks', $selection->remarks ?? '') }}</textarea>
            </div>

            <button class="button" type="submit">{{ $selection ? 'Update Approval' : 'Approve Punches' }}</button>
        </form>
    </main>
</body>
</html>
