<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meeting Room Bookings</title>
    <style>
        body { font-family: sans-serif; background: #f8fafc; color: #111827; }
        .container { max-width: 1100px; margin: 3rem auto; padding: 2rem; background: white; border-radius: .75rem; box-shadow: 0 10px 30px rgba(15,23,42,.08); }
        .toolbar, .nav, .filters, .view-tabs, .booking-form, .booking-actions { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; }
        .toolbar, .calendar-heading { justify-content: space-between; }
        .toolbar { margin-bottom: 1.5rem; }
        .button { display: inline-block; padding: .65rem .9rem; border-radius: .5rem; background: #111827; color: white; text-decoration: none; border: 0; cursor: pointer; }
        .secondary { background: #e2e8f0; color: #111827; }
        .filters { padding: 1rem; background: #f8fafc; border-radius: .6rem; margin: 1rem 0; }
        label { display: grid; gap: .35rem; }
        input, select { padding: .6rem; border: 1px solid #cbd5e1; border-radius: .4rem; }
        .notice { padding: .75rem; background: #dcfce7; border-radius: .5rem; }
        .errors { padding: .75rem 2rem; color: #991b1b; background: #fee2e2; border-radius: .5rem; }
        .view-tabs a { padding: .55rem .8rem; border-radius: .4rem; color: #111827; background: #e2e8f0; text-decoration: none; }
        .view-tabs a[aria-current="page"] { color: white; background: #1d4ed8; }
        .calendar-heading { display: flex; flex-wrap: wrap; align-items: center; margin: 1rem 0; }
        .day-schedule { border: 1px solid #e5e7eb; border-radius: .5rem; overflow: hidden; }
        .hour-row { display: grid; grid-template-columns: 6rem 1fr; min-height: 3.5rem; border-bottom: 1px solid #e5e7eb; }
        .hour-row:last-child { border-bottom: 0; }
        .hour-label { padding: .65rem; color: #475569; background: #f8fafc; border-right: 1px solid #e5e7eb; }
        .hour-events { padding: .35rem .65rem; display: flex; flex-wrap: wrap; gap: .5rem; align-items: flex-start; }
        .booking-chip { display: inline-grid; gap: .2rem; padding: .45rem .6rem; background: #dbeafe; border-left: 3px solid #2563eb; border-radius: .3rem; }
        .booking-chip small, .muted { color: #475569; }
        .week-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: .5rem; }
        .week-day { min-height: 12rem; padding: .6rem; border: 1px solid #e5e7eb; border-radius: .5rem; background: #fff; }
        .week-day.today { border-color: #2563eb; }
        .week-day h3 { margin: 0 0 .75rem; font-size: .95rem; }
        .week-day .booking-chip { display: grid; min-width: 0; margin-bottom: .45rem; overflow-wrap: anywhere; font-size: .85rem; }
        .month-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: .35rem; }
        .month-weekday { padding: .5rem; text-align: center; font-weight: 600; color: #475569; }
        .month-day { min-height: 7rem; padding: .45rem; border: 1px solid #e5e7eb; border-radius: .35rem; overflow: hidden; }
        .month-day.other-month { background: #f8fafc; color: #94a3b8; }
        .month-day a { color: inherit; text-decoration: none; }
        .month-day .booking-chip { display: flex; margin-top: .35rem; padding: .3rem; font-size: .75rem; }
        .content-grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr); gap: 1.5rem; align-items: start; }
        .panel { padding: 1rem; border: 1px solid #e5e7eb; border-radius: .6rem; }
        .panel h2 { margin-top: 0; }
        .my-booking { padding: .75rem 0; border-top: 1px solid #e5e7eb; }
        .my-booking form { margin-top: .4rem; }
        .empty { padding: 1rem; color: #475569; background: #f8fafc; border-radius: .5rem; }
        @media (max-width: 850px) { .week-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 640px) {
            .container { margin: 1rem; padding: 1rem; }
            .toolbar { align-items: stretch; flex-direction: column; }
            .content-grid { grid-template-columns: 1fr; }
            .week-grid { grid-template-columns: 1fr; }
            .month-grid { gap: .15rem; }
            .month-day { min-height: 4.5rem; padding: .25rem; font-size: .82rem; }
            .hour-row { grid-template-columns: 4.5rem 1fr; }
        }
    </style>
</head>
<body>
    <main class="container">
        <div class="toolbar">
            <div>
                <h1>Meeting Room Bookings</h1>
                <p>Find an available room and reserve a time.</p>
            </div>
            <div class="nav">
                @if (in_array(auth()->user()->role?->slug, ['employee', 'supervisor'], true))
                    <a class="button secondary" href="{{ route('employee.attendance.self') }}">My Attendance</a>
                @endif
                <a class="button secondary" href="{{ route('dashboard') }}">Dashboard</a>
                @if (auth()->user()->role?->slug === 'superadmin')
                    <a class="button secondary" href="{{ route('meeting-rooms.manage') }}">Manage Rooms</a>
                @endif
            </div>
        </div>

        @if (session('success'))
            <p class="notice" role="status">{{ session('success') }}</p>
        @endif
        @if ($errors->any())
            <ul class="errors" role="alert">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <div class="view-tabs" aria-label="Calendar view">
            @foreach (['day' => 'Day / Hour', 'week' => 'Week', 'month' => 'Month'] as $viewKey => $viewLabel)
                <a href="{{ route('meeting-rooms.index', ['date' => $date->toDateString(), 'view' => $viewKey, 'room_id' => $selectedRoomId]) }}"
                   @if ($calendarView === $viewKey) aria-current="page" @endif>{{ $viewLabel }}</a>
            @endforeach
        </div>

        <form class="filters" method="GET" action="{{ route('meeting-rooms.index') }}">
            <input type="hidden" name="view" value="{{ $calendarView }}">
            <label for="calendar-date">Date
                <input id="calendar-date" type="date" name="date" value="{{ $date->toDateString() }}" required>
            </label>
            <label for="room-filter">Room
                <select id="room-filter" name="room_id">
                    <option value="">All active rooms</option>
                    @foreach ($rooms as $room)
                        <option value="{{ $room->id }}" @selected((string) $selectedRoomId === (string) $room->id)>{{ $room->name }}</option>
                    @endforeach
                </select>
            </label>
            <button class="button" type="submit">Show Calendar</button>
        </form>

        <div class="calendar-heading">
            <div class="nav">
                <a class="button secondary" href="{{ route('meeting-rooms.index', ['date' => $previousDate->toDateString(), 'view' => $calendarView, 'room_id' => $selectedRoomId]) }}">Previous</a>
                <a class="button secondary" href="{{ route('meeting-rooms.index', ['date' => now()->toDateString(), 'view' => $calendarView, 'room_id' => $selectedRoomId]) }}">Today</a>
                <a class="button secondary" href="{{ route('meeting-rooms.index', ['date' => $nextDate->toDateString(), 'view' => $calendarView, 'room_id' => $selectedRoomId]) }}">Next</a>
            </div>
            <h2>
                @if ($calendarView === 'day')
                    {{ $date->format('l, F j, Y') }}
                @elseif ($calendarView === 'week')
                    {{ $calendarDates->first()->format('M j') }} – {{ $calendarDates->last()->format('M j, Y') }}
                @else
                    {{ $date->format('F Y') }}
                @endif
            </h2>
        </div>

        @if ($rooms->isEmpty())
            <p class="empty">There are no active meeting rooms available. Please contact an administrator.</p>
        @elseif ($calendarView === 'day')
            <div class="day-schedule">
                @foreach (range(0, 23) as $hour)
                    @php
                        $hourStart = $date->copy()->setTime($hour, 0);
                        $hourEnd = $hourStart->copy()->addHour();
                        $hourBookings = $bookings->filter(fn ($booking) => $booking->starts_at->lt($hourEnd) && $booking->ends_at->gt($hourStart));
                    @endphp
                    <div class="hour-row">
                        <div class="hour-label">{{ $hourStart->format('g:i A') }}</div>
                        <div class="hour-events">
                            @foreach ($hourBookings as $booking)
                                <span class="booking-chip">
                                    <strong>{{ $booking->title }}</strong>
                                    <small>{{ $booking->starts_at->format('g:i A') }}–{{ $booking->ends_at->format('g:i A') }} · {{ $booking->room?->name }} · Booked by {{ $booking->booked_by_name }}</small>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @elseif ($calendarView === 'week')
            <div class="week-grid">
                @foreach ($calendarDates as $calendarDay)
                    <section class="week-day @if ($calendarDay->isToday()) today @endif">
                        <h3>{{ $calendarDay->format('D, M j') }}</h3>
                        @forelse ($bookingsByDate->get($calendarDay->toDateString(), collect()) as $booking)
                            <span class="booking-chip">
                                <strong>{{ $booking->title }}</strong>
                                <small>{{ $booking->starts_at->format('g:i A') }}–{{ $booking->ends_at->format('g:i A') }} · {{ $booking->room?->name }} · Booked by {{ $booking->booked_by_name }}</small>
                            </span>
                        @empty
                            <span class="muted">No bookings</span>
                        @endforelse
                    </section>
                @endforeach
            </div>
        @else
            <div class="month-grid">
                @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                    <div class="month-weekday">{{ $weekday }}</div>
                @endforeach
                @foreach ($calendarDates as $calendarDay)
                    <div class="month-day @if ($calendarDay->month !== $date->month) other-month @endif">
                        <a href="{{ route('meeting-rooms.index', ['date' => $calendarDay->toDateString(), 'view' => 'day', 'room_id' => $selectedRoomId]) }}">{{ $calendarDay->day }}</a>
                        @foreach ($bookingsByDate->get($calendarDay->toDateString(), collect()) as $booking)
                            <span class="booking-chip">
                                {{ $booking->starts_at->format('g:i A') }} · {{ $booking->title }}
                                · Booked by {{ $booking->booked_by_name }}
                                <small>{{ $booking->room?->name }}</small>
                            </span>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif

        <div class="content-grid" style="margin-top: 1.5rem;">
            <section class="panel">
                <h2>Book a room</h2>
                @if ($rooms->isNotEmpty())
                    <form class="booking-form" method="POST" action="{{ route('meeting-room-bookings.store') }}">
                        @csrf
                        <label for="meeting_room_id">Room
                            <select id="meeting_room_id" name="meeting_room_id" required>
                                @foreach ($rooms as $room)
                                    <option value="{{ $room->id }}" @selected((string) old('meeting_room_id', $selectedRoomId) === (string) $room->id)>{{ $room->name }}{{ $room->location ? ' — '.$room->location : '' }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label for="title">Meeting title
                            <input id="title" name="title" value="{{ old('title') }}" maxlength="150" required>
                        </label>
                        <label for="starts_at">Start
                            <input id="starts_at" type="datetime-local" name="starts_at" value="{{ old('starts_at', $defaultStart->format('Y-m-d\TH:i')) }}" required>
                        </label>
                        <label for="ends_at">End
                            <input id="ends_at" type="datetime-local" name="ends_at" value="{{ old('ends_at', $defaultEnd->format('Y-m-d\TH:i')) }}" required>
                        </label>
                        <button class="button" type="submit">Book Room</button>
                    </form>
                @endif
            </section>

            <section class="panel">
                <h2>My upcoming bookings</h2>
                @forelse ($myBookings as $booking)
                    <div class="my-booking">
                        <strong>{{ $booking->title }}</strong>
                        <div>{{ $booking->room?->name }}</div>
                        <div class="muted">{{ $booking->starts_at->format('D, M j, Y · g:i A') }}–{{ $booking->ends_at->format('g:i A') }}</div>
                        @if ($booking->starts_at->isFuture())
                            <form method="POST" action="{{ route('meeting-room-bookings.cancel', $booking) }}">
                                @csrf
                                <button class="button secondary" type="submit">Cancel booking</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="muted">You have no upcoming bookings.</p>
                @endforelse
            </section>
        </div>
    </main>
</body>
</html>
