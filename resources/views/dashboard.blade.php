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
        .pagination { display: flex; justify-content: flex-end; }
        .pagination nav { display: inline-flex; gap: 0.5rem; }
        .pagination svg,
        .pagination .w-5,
        .pagination .h-5 { width: 1.25rem; height: 1.25rem; }
        .pagination svg { display: inline-block; vertical-align: middle; }
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
                    <select id="department" name="department">
                        <option value="">All</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept->department_id }}" {{ request('department') == $dept->department_id ? 'selected' : '' }}>{{ $dept->department_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                        <option value="2" {{ request('status') === '2' ? 'selected' : '' }}>Disabled</option>
                    </select>
                </div>
                <div class="actions">
                    <button type="submit" class="button">Apply</button>
                    <a class="button button-secondary" href="{{ route('dashboard') }}">Clear</a>
                </div>
            </form>

            @if (isset($employees) && $employees->isNotEmpty())
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>Join Date (ENG)</th>
                                <th>Join Date (NPT)</th>
                                <th>Status</th>
                                <th>Salary</th>
                                <th>Hours</th>
                                <th>Part Time</th>
                                <th>Dept</th>
                                <th>Gender</th>
                                <th>Attendance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($employees as $employee)
                                <tr>
                                    <td>{{ $employee->id }}</td>
                                    <td>{{ $employee->user_id }}</td>
                                    <td>{{ $employee->employee_name }}</td>
                                    <td>{{ optional($employee->join_date_eng)->format('Y-m-d H:i') }}</td>
                                    <td>{{ $employee->join_date_npt }}</td>
                                    <td>{{ $employee->status }}</td>
                                    <td>{{ $employee->salary }}</td>
                                    <td>{{ $employee->working_hours }}</td>
                                    <td>{{ $employee->part_time ? 'Yes' : 'No' }}</td>
                                    <td>{{ $employee->department?->department_name ?? $employee->department_id }}</td>
                                    <td>{{ $employee->gender }}</td>
                                    <td>
                                        <a href="{{ route('employee.attendance', $employee) }}">Attendance</a>
                                        |
                                        <a href="{{ route('employees.edit', $employee) }}">Edit</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="pagination">
                    {{ $employees->links() }}
                </div>
            @else
                <p>No employees found yet.</p>
            @endif
        </section>
    </div>
</body>
</html>
