<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance for {{ $employee->employee_name }}</title>
    <style>
        body { font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, sans-serif; background: #eff6ff; color: #0f172a; }
        .container { max-width: 980px; margin: 3rem auto; padding: 2rem; background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); border-radius: 1rem; box-shadow: 0 25px 70px rgba(15, 23, 42, 0.12); }
        .toolbar { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem; align-items: center; margin-bottom: 1.5rem; }
        .toolbar h1 { margin: 0; font-size: 1.9rem; letter-spacing: -0.03em; }
        .toolbar p { margin: 0.35rem 0 0; color: #475569; }
        .button { display: inline-flex; align-items: center; justify-content: center; padding: 0.85rem 1.25rem; background: #1d4ed8; color: white; text-decoration: none; border-radius: 0.75rem; border: none; cursor: pointer; font-weight: 600; transition: background 160ms ease-in-out; }
        .button:hover { background: #2563eb; }
        .button-secondary { background: #e2e8f0; color: #0f172a; }
        .button-secondary:hover { background: #cbd5e1; }
        .calendar-actions { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin-bottom: 1.25rem; }
        .calendar-actions label { display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.9rem; color: #334155; }
        .calendar-actions select { min-width: 150px; padding: 0.75rem 0.85rem; border-radius: 0.75rem; border: 1px solid #cbd5e1; background: #f8fafc; color: #0f172a; }
        .calendar-card { border: 1px solid #e2e8f0; border-radius: 1rem; overflow: hidden; background: #ffffff; box-shadow: 0 0 0 1px rgba(203, 213, 225, 0.35); }
        .calendar-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
        .calendar-title { margin: 0; font-size: 1.1rem; font-weight: 700; }
        .calendar-subtitle { margin: 0.35rem 0 0; color: #64748b; font-size: 0.95rem; }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 0.75rem; padding: 1.25rem; }
        .calendar-weekday { text-align: center; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #475569; padding: 0.65rem 0; }
        .calendar-day { min-height: 130px; border-radius: 1rem; padding: 0.9rem; background: #f8fafc; display: flex; flex-direction: column; justify-content: flex-start; gap: 0.75rem; border: 1px solid transparent; transition: border-color 160ms ease, transform 160ms ease; }
        .calendar-day:hover { transform: translateY(-1px); border-color: #93c5fd; }
        .calendar-day.empty { background: #f1f5f9; color: #94a3b8; box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.12); }
        .calendar-day-date { display: inline-flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 0.75rem; background: #e2e8f0; color: #0f172a; font-weight: 700; }
        .attendance-chip { display: inline-flex; align-items: center; justify-content: center; width: fit-content; padding: 0.45rem 0.65rem; border-radius: 9999px; font-size: 0.78rem; font-weight: 600; text-transform: capitalize; }
        .attendance-chip.present { background: #dcfce7; color: #166534; }
        .attendance-chip.absent { background: #fee2e2; color: #991b1b; }
        .attendance-chip.remote { background: #ede9fe; color: #7c3aed; }
        .attendance-chip.no-entry { background: #e2e8f0; color: #475569; }
        .legend { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem; padding: 1rem 1.25rem; background: #f8fafc; border-top: 1px solid #e2e8f0; }
        .legend-item { display: inline-flex; align-items: center; gap: 0.55rem; font-size: 0.9rem; color: #334155; }
        .legend-bullet { width: 0.9rem; height: 0.9rem; border-radius: 9999px; display: inline-block; }
        .legend-bullet.present { background: #dcfce7; }
        .legend-bullet.absent { background: #fee2e2; }
        .legend-bullet.remote { background: #ede9fe; }
        .legend-bullet.no-entry { background: #e2e8f0; }
        @media (max-width: 900px) {
            .calendar-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        }
        @media (max-width: 640px) {
            .toolbar, .calendar-header { flex-direction: column; align-items: stretch; }
            .calendar-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .calendar-actions { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="toolbar">
            <div>
                <h1>Attendance for {{ $employee->employee_name }}</h1>
                <p>Department: {{ $employee->department?->department_name ?? 'N/A' }}</p>
            </div>
            <a class="button button-secondary" href="{{ route('dashboard') }}">Back to Dashboard</a>
        </div>

        <section>
            <div class="calendar-card">
                <div class="calendar-header">
                    <div>
                        <p class="calendar-title">Monthly Attendance</p>
                        <p class="calendar-subtitle">{{ $monthStart->format('F Y') }} for {{ $employee->employee_name }}</p>
                    </div>
                    <form method="GET" action="{{ route('employee.attendance', $employee) }}" class="calendar-actions">
                        <label>
                            Month
                            <select name="month" onchange="this.form.submit()">
                                @foreach (range(1, 12) as $num)
                                    <option value="{{ $num }}" {{ $monthStart->month === $num ? 'selected' : '' }}>{{ Date::createFromDate($monthStart->year, $num, 1)->format('F') }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            Year
                            <select name="year" onchange="this.form.submit()">
                                @foreach (range(date('Y') - 2, date('Y') + 1) as $year)
                                    <option value="{{ $year }}" {{ $monthStart->year === $year ? 'selected' : '' }}>{{ $year }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button class="button" type="submit">Go</button>
                    </form>
                </div>

                <div class="calendar-grid">
                    <div class="calendar-weekday">Sun</div>
                    <div class="calendar-weekday">Mon</div>
                    <div class="calendar-weekday">Tue</div>
                    <div class="calendar-weekday">Wed</div>
                    <div class="calendar-weekday">Thu</div>
                    <div class="calendar-weekday">Fri</div>
                    <div class="calendar-weekday">Sat</div>

                @php
                    $days = [];
                    $startDay = $monthStart->dayOfWeek;
                    for ($i = 0; $i < $startDay; $i++) {
                        $days[] = null;
                    }
                    for ($day = 1; $day <= $monthEnd->day; $day++) {
                        $days[] = $monthStart->copy()->day($day);
                    }
                @endphp

                @foreach ($days as $day)
                    @if ($day === null)
                        <div class="calendar-day empty"></div>
                    @else
                        @php
                            $dateKey = $day->format('Y-m-d');
                            $dayRecords = $attendances->filter(fn($row) => data_get($row, 'attendance_date') === $dateKey);
                        @endphp
                        <div class="calendar-day">
                            <div class="calendar-day-date">{{ $day->format('j') }}</div>
                            @forelse ($dayRecords as $record)
                                <span class="attendance-chip {{ Str::slug(data_get($record, 'status', 'present')) }}">
                                    {{ ucfirst(data_get($record, 'status', 'Present')) }}
                                </span>
                            @empty
                                <span class="attendance-chip no-entry">No entry</span>
                            @endforelse
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="legend">
                <div class="legend-item"><span class="legend-bullet present"></span> Present</div>
                <div class="legend-item"><span class="legend-bullet absent"></span> Absent</div>
                <div class="legend-item"><span class="legend-bullet remote"></span> Remote</div>
                <div class="legend-item"><span class="legend-bullet no-entry"></span> No entry</div>
            </div>
        </section>
    </div>
</body>
</html>
