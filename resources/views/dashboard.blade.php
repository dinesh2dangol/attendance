<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <style>
        body { font-family: sans-serif; background: #f8fafc; color: #111827; }
        .container { max-width: 900px; margin: 4rem auto; padding: 2rem; background: white; border-radius: 0.75rem; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); }
        .toolbar { display: flex; justify-content: space-between; gap: 1rem; align-items: center; margin-bottom: 1.5rem; }
        .button { display: inline-flex; padding: 0.75rem 1rem; background: #111827; color: white; text-decoration: none; border-radius: 0.5rem; border: none; cursor: pointer; }
        .button-secondary { background: #f3f4f6; color: #111827; }
        .filters { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .field-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .field-group input,
        .field-group select { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; background: #f9fafb; }
        .actions { display: flex; gap: 0.75rem; align-items: flex-end; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
        th, td { padding: 0.75rem 0.85rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f3f4f6; font-weight: 600; }
        tbody tr:hover { background: #f9fafb; }

        .desktop-table { display: block; }
        .mobile-list { display: none; }
        .mobile-employee-item { margin-bottom: 0.75rem; }
        .swipe-shell {
            position: relative;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            border-radius: 0.85rem;
            background: #fff;
            min-height: 155px;
        }
        .swipe-track {
            display: flex;
            width: 200%;
            transition: transform 0.28s ease;
        }
        .swipe-main,
        .swipe-detail {
            flex: 0 0 50%;
            width: 50%;
            box-sizing: border-box;
            background: #fff;
        }
        .swipe-main {
            padding: 0.9rem 1rem;
        }
        .swipe-detail {
            padding: 0.9rem 1rem;
            background: #f8fafc;
            border-left: 1px solid #e5e7eb;
        }
        .swipe-shell.is-open .swipe-track {
            transform: translateX(-50%);
        }
        .mobile-meta {
            display: grid;
            gap: 0.5rem;
            font-size: 0.88rem;
            color: #374151;
        }
        .detail-label {
            display: block;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #6b7280;
            margin-bottom: 0.25rem;
        }
        .swipe-detail p {
            margin: 0;
            line-height: 1.5;
            font-size: 0.88rem;
            color: #374151;
        }
        .swipe-detail .date-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem 0.5rem;
            margin-top: 0.35rem;
        }
        .swipe-detail a {
            color: #1d4ed8;
            text-decoration: none;
        }
        .mobile-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.8rem;
        }

        .pagination { display: flex; justify-content: flex-end; }
        .pagination nav { display: inline-flex; gap: 0.5rem; }
        .pagination svg,
        .pagination .w-5,
        .pagination .h-5 { width: 1.25rem; height: 1.25rem; }
        .pagination svg { display: inline-block; vertical-align: middle; }
        .page-size-wrap {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        .page-size-wrap label {
            font-size: 0.9rem;
            color: #374151;
        }
        .page-size-wrap select {
            padding: 0.55rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            background: #f9fafb;
        }

        @media (max-width: 768px) {
            body { margin: 0; }
            .container {
                max-width: 100%;
                margin: 1rem;
                padding: 1rem;
            }
            .toolbar {
                flex-direction: column;
                align-items: flex-start;
            }
            .filters {
                grid-template-columns: 1fr;
            }
            .actions {
                width: 100%;
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.75rem;
            }
            .button,
            .button-secondary {
                width: 100%;
                justify-content: center;
                text-align: center;
            }
            .desktop-table {
                display: none;
            }
            .mobile-list {
                display: block;
            }
            .table-wrap {
                margin: 0 -0.25rem;
            }
            th, td {
                padding: 0.65rem 0.5rem;
                font-size: 0.9rem;
            }
            td[colspan="8"] {
                line-height: 1.6;
            }
            td[colspan="8"] a {
                display: inline-block;
                margin-bottom: 0.15rem;
            }
        }

        @media (max-width: 480px) {
            .container {
                margin: 0.75rem;
                padding: 0.75rem;
            }
            .actions {
                grid-template-columns: 1fr;
            }
            .toolbar h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="toolbar">
            <div>
                <h1>Dashboard</h1>
                <p>Welcome back, {{ auth()->user()->name }}.</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="button" type="submit">Log out</button>
            </form>
        </div>

        {{-- Manage Employee Logins disabled temporarily --}}

        <section>
            <form method="GET" action="{{ route('dashboard') }}" class="filters">
                <div class="field-group">
                    <label for="search">Search</label>
                    <input id="search" name="search" type="text" value="{{ request('search') }}" placeholder="Employee name" />
                </div>
                <div class="field-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender">
                        <option value="">All</option>
                        <option value="male" {{ request('gender') === 'male' ? 'selected' : '' }}>Male</option>
                        <option value="female" {{ request('gender') === 'female' ? 'selected' : '' }}>Female</option>
                        <option value="other" {{ request('gender') === 'other' ? 'selected' : '' }}>Other</option>
                    </select>
                </div>
                <div class="field-group">
                    <label for="department">Department</label>
                    <select id="department" name="department" onchange="this.form.submit()">
                        <option value="">All</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept->department_id }}" 
                                {{ (string)$department === (string)$dept->department_id ? 'selected' : '' }}>
                                {{ $dept->department_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="field-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="actions">
                    <button type="submit" class="button">Apply</button>
                    <a class="button button-secondary" href="{{ route('dashboard') }}">Clear</a>
                </div>
            </form>

            @if (isset($employees) && $employees->isNotEmpty())
                <div class="desktop-table">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User ID</th>
                                    <th>Name</th>
                                    <th>Join Date (ENG)</th>
                                    <th>Status</th>
                                    <th>Dept</th>
                                    <th>Gender</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($employees as $employee)
                                    @php
                                        $absentDates = $absentDatesByUser[$employee->user_id] ?? [];
                                        $leaveDates = $leaveDatesByUser[$employee->user_id] ?? [];
                                    @endphp
                                    <tr>
                                        <td>{{ $employee->id }}</td>
                                        <td>{{ $employee->user_id }}</td>
                                        <td>{{ $employee->employee_name }}</td>
                                        <td>{{ optional($employee->join_date_eng)->format('Y-m-d') }}</td>
                                        <td>{{ $employee->status }}</td>
                                        <td>{{ $employee->department?->department_name ?? $employee->department_id }}</td>
                                        <td>{{ $employee->gender }}</td>
                                        <td>
                                            @if(auth()->user()?->role?->slug === 'employee')
                                                <a href="{{ route('employee.attendance.self') }}">Attendance</a>
                                            @else
                                                <a href="{{ route('employee.attendance', array_merge(['employee' => $employee], ['back' => request()->getQueryString()])) }}">Attendance</a>
                                            @endif
                                            |
                                            <a href="{{ route('employees.edit', array_merge(['employee' => $employee], ['back' => request()->getQueryString()])) }}">Edit</a>
                                            |
                                            <a href="{{ route('leaves.index', array_merge(['user_id' => $employee->user_id], ['back' => request()->getQueryString()])) }}">Leaves</a>
                                            |
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="8">
                                            <strong>Absent Dates:</strong>
                                            @if (!empty($absentDates))
                                                @foreach ($absentDates as $date)
                                                    @php
                                                        $absentDate = \Carbon\Carbon::parse($date);
                                                    @endphp
                                                    @if(auth()->user()?->role?->slug === 'employee')
                                                        <a href="{{ route('employee.attendance.self', ['month' => $absentDate->month, 'year' => $absentDate->year]) }}">{{ $date }}</a>@if (! $loop->last), @endif
                                                    @else
                                                        <a href="{{ route('employee.attendance', array_merge(['employee' => $employee, 'month' => $absentDate->month, 'year' => $absentDate->year], ['back' => request()->getQueryString()])) }}">{{ $date }}</a>@if (! $loop->last), @endif
                                                    @endif
                                                @endforeach
                                            @else
                                                None
                                            @endif
                                            <br>
                                            <strong>Leave Dates:</strong>
                                            @if (!empty($leaveDates))
                                                @foreach ($leaveDates as $leave)
                                                    @php
                                                        $leaveDate = \Carbon\Carbon::parse($leave['date']);
                                                    @endphp
                                                    @if(auth()->user()?->role?->slug === 'employee')
                                                        <a href="{{ route('employee.attendance.self', ['month' => $leaveDate->month, 'year' => $leaveDate->year]) }}">{{ $leave['date'] }}{{ $leave['pending'] ? ' (P)' : '' }}</a>@if (! $loop->last), @endif
                                                    @else
                                                        <a href="{{ route('employee.attendance', array_merge(['employee' => $employee, 'month' => $leaveDate->month, 'year' => $leaveDate->year], ['back' => request()->getQueryString()])) }}">{{ $leave['date'] }}{{ $leave['pending'] ? ' (P)' : '' }}</a>@if (! $loop->last), @endif
                                                    @endif
                                                @endforeach
                                            @else
                                                None
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mobile-list">
                    @foreach ($employees as $employee)
                        @php
                            $absentDates = $absentDatesByUser[$employee->user_id] ?? [];
                            $leaveDates = $leaveDatesByUser[$employee->user_id] ?? [];
                        @endphp
                        <div class="mobile-employee-item">
                            <div class="swipe-shell" data-swipe-shell>
                                <div class="swipe-track">
                                    <div class="swipe-main">
                                        <div class="mobile-meta">
                                            <div><span class="detail-label">Name</span><strong>{{ $employee->employee_name }}</strong></div>
                                            <div><span class="detail-label">Department</span><strong>{{ $employee->department?->department_name ?? $employee->department_id }}</strong></div>
                                            <div><span class="detail-label">Status</span><strong>{{ $employee->status }}</strong></div>
                                        </div>
                                        <div class="mobile-actions">
                                            @if(auth()->user()?->role?->slug === 'employee')
                                                <a class="button button-secondary" href="{{ route('employee.attendance.self') }}">Attendance</a>
                                            @else
                                                <a class="button button-secondary" href="{{ route('employee.attendance', array_merge(['employee' => $employee], ['back' => request()->getQueryString()])) }}">Attendance</a>
                                            @endif
                                            <a class="button button-secondary" href="{{ route('employees.edit', array_merge(['employee' => $employee], ['back' => request()->getQueryString()])) }}">Edit</a>
                                        </div>
                                    </div>
                                    <div class="swipe-detail">
                                        <span class="detail-label">Absent &amp; Leave</span>
                                        @if (!empty($absentDates) || !empty($leaveDates))
                                            <div class="date-list">
                                                @foreach ($absentDates as $date)
                                                    @php
                                                        $absentDate = \Carbon\Carbon::parse($date);
                                                    @endphp
                                                    @if(auth()->user()?->role?->slug === 'employee')
                                                        <a href="{{ route('employee.attendance.self', ['month' => $absentDate->month, 'year' => $absentDate->year]) }}">{{ $date }}</a>
                                                    @else
                                                        <a href="{{ route('employee.attendance', array_merge(['employee' => $employee, 'month' => $absentDate->month, 'year' => $absentDate->year], ['back' => request()->getQueryString()])) }}">{{ $date }}</a>
                                                    @endif
                                                @endforeach

                                                @foreach ($leaveDates as $leave)
                                                    @php
                                                        $leaveDate = \Carbon\Carbon::parse($leave['date']);
                                                    @endphp
                                                    @if(auth()->user()?->role?->slug === 'employee')
                                                        <a href="{{ route('employee.attendance.self', ['month' => $leaveDate->month, 'year' => $leaveDate->year]) }}">{{ $leave['date'] }}{{ $leave['pending'] ? ' (P)' : '' }}</a>
                                                    @else
                                                        <a href="{{ route('employee.attendance', array_merge(['employee' => $employee, 'month' => $leaveDate->month, 'year' => $leaveDate->year], ['back' => request()->getQueryString()])) }}">{{ $leave['date'] }}{{ $leave['pending'] ? ' (P)' : '' }}</a>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @else
                                            <p>None</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="page-size-wrap">
                    <form method="GET" action="{{ route('dashboard') }}">
                        @foreach (request()->except('per_page') as $key => $value)
                            @if (is_array($value))
                                @foreach ($value as $subValue)
                                    <input type="hidden" name="{{ $key }}[]" value="{{ $subValue }}">
                                @endforeach
                            @else
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <label for="per_page">Rows per page</label>
                        <select id="per_page" name="per_page" onchange="this.form.submit()">
                            @foreach ([10, 25, 50, 100] as $size)
                                <option value="{{ $size }}" {{ (int) ($perPage ?? 10) === $size ? 'selected' : '' }}>{{ $size }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>

                <div class="pagination">
                    {{ $employees->links() }}
                </div>
            @else
                <p>No employees found yet.</p>
            @endif
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const shells = document.querySelectorAll('[data-swipe-shell]');

            shells.forEach(function (shell) {
                let startX = 0;
                let currentX = 0;
                let dragging = false;

                const open = function () {
                    shell.classList.add('is-open');
                };

                const close = function () {
                    shell.classList.remove('is-open');
                };

                shell.addEventListener('touchstart', function (event) {
                    startX = event.touches[0].clientX;
                    currentX = startX;
                    dragging = true;
                }, { passive: true });

                shell.addEventListener('touchmove', function (event) {
                    if (!dragging) return;
                    currentX = event.touches[0].clientX;
                }, { passive: true });

                shell.addEventListener('touchend', function () {
                    if (!dragging) return;

                    const diff = currentX - startX;
                    if (diff < -50) {
                        open();
                    } else if (diff > 50) {
                        close();
                    } else {
                        shell.classList.contains('is-open') ? close() : open();
                    }

                    dragging = false;
                });

                shell.addEventListener('click', function (event) {
                    const interactiveTag = event.target.closest('button, a, input, select, textarea, form');
                    if (interactiveTag) {
                        return;
                    }

                    shell.classList.toggle('is-open');
                });
            });
        });
    </script>
</body>
</html>
