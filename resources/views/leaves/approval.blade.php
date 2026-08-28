<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Approval</title>
    <style>
        body { font-family: Inter, system-ui, sans-serif; background: #f8fafc; color: #0f172a; }
        .container { max-width: 1000px; margin: 3rem auto; padding: 2rem; background: white; border-radius: 1rem; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12); }
        .toolbar { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem; align-items: center; margin-bottom: 1.5rem; }
        .button { display: inline-flex; align-items: center; justify-content: center; padding: 0.8rem 1.1rem; border-radius: 0.75rem; border: none; cursor: pointer; font-weight: 600; color: white; background: #2563eb; text-decoration: none; }
        .button:hover { background: #1d4ed8; }
        .button-secondary { background: #e2e8f0; color: #0f172a; }
        .button-secondary:hover { background: #cbd5e1; }
        .grid { display: grid; gap: 1.25rem; grid-template-columns: 1fr 1fr; margin-bottom: 1.5rem; }
        .card { padding: 1.25rem; border: 1px solid #e2e8f0; border-radius: 1rem; background: #ffffff; }
        .card h2 { margin: 0 0 0.75rem; font-size: 1rem; color: #0f172a; }
        .badge { display: inline-flex; align-items: center; justify-content: center; padding: 0.45rem 0.75rem; border-radius: 9999px; font-size: 0.85rem; font-weight: 700; color: white; }
        .badge-pending { background: #f59e0b; }
        .badge-approved { background: #16a34a; }
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; min-width: 640px; border-collapse: collapse; }
        th, td { padding: 0.9rem 1rem; border-bottom: 1px solid #e2e8f0; text-align: left; }
        th { background: #f8fafc; font-weight: 700; }
        td:last-child { white-space: nowrap; }
        .form-row { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .form-row label { display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.95rem; color: #475569; }
        .form-row select { padding: 0.75rem 0.85rem; border: 1px solid #cbd5e1; border-radius: 0.75rem; background: #f8fafc; }
        .message { margin-bottom: 1rem; padding: 1rem; border-radius: 0.75rem; }
        .message.success { background: #d1fae5; color: #065f46; }
        .message.error { background: #fee2e2; color: #991b1b; }

        .mobile-list { display: none; }
        .mobile-leave-item { margin-bottom: 0.75rem; }
        .swipe-shell {
            position: relative;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 0.85rem;
            background: #fff;
            min-height: 150px;
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
            position: relative;
            z-index: 2;
            padding: 0.9rem 1rem;
        }
        .swipe-detail {
            padding: 0.9rem 1rem;
            background: #f8fafc;
            border-left: 1px solid #e2e8f0;
        }
        .swipe-shell.is-open .swipe-track {
            transform: translateX(-50%);
        }
        .mobile-meta {
            display: grid;
            gap: 0.45rem;
            font-size: 0.88rem;
            color: #334155;
        }
        .mobile-meta strong {
            color: #0f172a;
        }
        .mobile-action {
            margin-top: 0.75rem;
        }
        .detail-label {
            display: block;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #64748b;
            margin-bottom: 0.35rem;
        }
        .swipe-detail p {
            margin: 0;
            line-height: 1.5;
            font-size: 0.88rem;
            color: #334155;
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
            .button {
                width: 100%;
            }
            .grid,
            .form-row {
                grid-template-columns: 1fr;
            }
            .card {
                padding: 1rem;
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
                padding: 0.7rem 0.55rem;
                font-size: 0.9rem;
            }
            td:last-child {
                white-space: normal;
            }
        }

        @media (max-width: 480px) {
            .container {
                margin: 0.75rem;
                padding: 0.75rem;
            }
            .toolbar h1 {
                font-size: 1.6rem;
            }
            .button {
                padding: 0.75rem 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="toolbar">
            <div>
                <h1>Leave Approval</h1>
                <p>Review pending leaves and see totals for absent days and leaves.</p>
            </div>
            <a class="button button-secondary" href="{{ route('dashboard') }}">Back to Dashboard</a>
        </div>

        @if(session('success'))
            <div class="message success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="message error">{{ session('error') }}</div>
        @endif

        <form method="GET" action="{{ route('leaves.approval') }}" class="form-row">
            <label>
                Employee
                <select name="user_id" onchange="this.form.submit()">
                    <option value="">All employees</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->user_id }}" {{ (string)$userId === (string)$emp->user_id ? 'selected' : '' }}>{{ $emp->employee_name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Month
                <select name="month" onchange="this.form.submit()">
                    @foreach(range(1, 12) as $num)
                        <option value="{{ $num }}" {{ $month === $num ? 'selected' : '' }}>{{ Date::createFromDate($year, $num, 1)->format('F') }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Year
                <select name="year" onchange="this.form.submit()">
                    @foreach(range(date('Y') - 1, date('Y') + 1) as $yearOption)
                        <option value="{{ $yearOption }}" {{ $year === $yearOption ? 'selected' : '' }}>{{ $yearOption }}</option>
                    @endforeach
                </select>
            </label>
        </form>

        <div class="grid">
            <div class="card">
                <h2>Total Absent</h2>
                <p style="font-size:2rem;font-weight:700;margin:0">{{ $absentCount }}</p>
            </div>
            <div class="card">
                <h2>Total Leaves</h2>
                <p style="font-size:2rem;font-weight:700;margin:0">{{ $totalLeaves }}</p>
            </div>
        </div>

        <div class="card" style="margin-bottom:1.5rem;">
            <h2>Pending Leaves</h2>
            @if($pendingLeaves->isEmpty())
                <p>No pending leaves found.</p>
            @else
                <div class="desktop-table">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Employee</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pendingLeaves as $leave)
                                    <tr>
                                        <td>{{ $leave->leave_id }}</td>
                                        <td>{{ $leave->employee?->employee_name ?? $leave->user_id }}</td>
                                        <td>{{ optional($leave->leave_date)->format('Y-m-d') }}</td>
                                        <td>{{ $leave->leave_type }}</td>
                                        <td>{{ Str::limit($leave->leave_description, 100) }}</td>
                                        <td>
                                            <form method="POST" action="{{ route('leaves.approve', $leave) }}" style="display:inline">
                                                @csrf
                                                <button type="submit" class="button">Approve</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mobile-list">
                    @foreach($pendingLeaves as $leave)
                        <div class="mobile-leave-item">
                            <div class="swipe-shell" data-swipe-shell>
                                <div class="swipe-track">
                                    <div class="swipe-main">
                                        <div class="mobile-meta">
                                            <div><span class="detail-label">Employee</span><strong>{{ $leave->employee?->employee_name ?? $leave->user_id }}</strong></div>
                                            <div><span class="detail-label">Date</span><strong>{{ optional($leave->leave_date)->format('Y-m-d') }}</strong></div>
                                            <div><span class="detail-label">Type</span><strong>{{ $leave->leave_type }}</strong></div>
                                        </div>
                                        <div class="mobile-action">
                                            <form method="POST" action="{{ route('leaves.approve', $leave) }}">
                                                @csrf
                                                <button type="submit" class="button">Approve</button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="swipe-detail">
                                        <span class="detail-label">Description</span>
                                        <p>{{ $leave->leave_description ?: 'No description provided.' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="card">
            <h2>Approved Leaves</h2>
            @if($approvedLeaves->isEmpty())
                <p>No approved leaves found.</p>
            @else
                <div class="desktop-table">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Employee</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($approvedLeaves as $leave)
                                    <tr>
                                        <td>{{ $leave->leave_id }}</td>
                                        <td>{{ $leave->employee?->employee_name ?? $leave->user_id }}</td>
                                        <td>{{ optional($leave->leave_date)->format('Y-m-d') }}</td>
                                        <td>
                                            @if($leave->employee && $leave->leave_date)
                                                @if(auth()->user()?->role?->slug === 'employee')
                                                    <a href="{{ route('employee.attendance.self', ['month' => $leave->leave_date->month, 'year' => $leave->leave_date->year]) }}">{{ $leave->leave_type }}</a>
                                                @else
                                                    <a href="{{ route('employee.attendance', ['employee' => $leave->employee, 'month' => $leave->leave_date->month, 'year' => $leave->leave_date->year]) }}">{{ $leave->leave_type }}</a>
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ Str::limit($leave->leave_description, 100) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mobile-list">
                    @foreach($approvedLeaves as $leave)
                        <div class="mobile-leave-item">
                            <div class="swipe-shell" data-swipe-shell>
                                <div class="swipe-track">
                                    <div class="swipe-main">
                                        <div class="mobile-meta">
                                            <div><span class="detail-label">Employee</span><strong>{{ $leave->employee?->employee_name ?? $leave->user_id }}</strong></div>
                                            <div><span class="detail-label">Date</span><strong>{{ optional($leave->leave_date)->format('Y-m-d') }}</strong></div>
                                            <div><span class="detail-label">Type</span><strong>{{ $leave->leave_type }}</strong></div>
                                        </div>
                                    </div>
                                    <div class="swipe-detail">
                                        <span class="detail-label">Description</span>
                                        <p>{{ $leave->leave_description ?: 'No description provided.' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
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
