<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Work From Home</title>
    <style>
        body { font-family: sans-serif; background: #f8fafc; color: #111827; }
        .container { max-width: 700px; margin: 4rem auto; padding: 2rem; background: white; border-radius: 0.75rem; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); }
        .field { display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem; }
        input, textarea { padding: 0.6rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
        .button { display: inline-block; padding: 0.6rem 0.9rem; background: #111827; color: white; border-radius: 0.5rem; border: none; text-decoration: none; cursor: pointer; }
        .errors { color: #b91c1c; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Request Work From Home</h1>
        <p>The request will remain pending until an admin or superadmin reviews it.</p>

        @if ($errors->any())
            <div class="errors">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ auth()->user()->role?->slug === 'superadmin' ? route('wfh-requests.store') : route('employee.wfh.store') }}">
            @csrf
            @if (auth()->user()->role?->slug === 'superadmin')
                @php
                    $selectedEmployeeId = old('user_id', $selectedUserId);
                    $selectedEmployee = $employees->firstWhere('user_id', $selectedEmployeeId);
                @endphp
                <div class="field">
                    <label for="employee_search">Employee</label>
                    <input id="employee_search" type="text" list="employee_options" autocomplete="off" placeholder="Type an employee name" value="{{ old('employee_search', $selectedEmployee ? $selectedEmployee->employee_name . ' (' . $selectedEmployee->user_id . ')' : '') }}" required>
                    <input id="user_id" name="user_id" type="hidden" value="{{ $selectedEmployeeId }}">
                    <datalist id="employee_options">
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->employee_name }} ({{ $employee->user_id }})" data-user-id="{{ $employee->user_id }}"></option>
                        @endforeach
                    </datalist>
                </div>
            @endif
            <div class="field">
                <label for="start_date">Start date</label>
                <input id="start_date" name="start_date" type="date" min="{{ now()->toDateString() }}" value="{{ old('start_date') }}" required>
            </div>
            <div class="field">
                <label for="end_date">End date</label>
                <input id="end_date" name="end_date" type="date" min="{{ now()->toDateString() }}" value="{{ old('end_date') }}" required>
            </div>
            <div class="field">
                <label for="reason">Reason</label>
                <textarea id="reason" name="reason" rows="4" maxlength="255" required>{{ old('reason') }}</textarea>
            </div>
            <button class="button" type="submit">Submit Request</button>
            <a class="button" href="{{ auth()->user()->role?->slug === 'superadmin' ? route('wfh-requests.index') : route('employee.wfh.index') }}">Cancel</a>
        </form>
    </div>
    @if (auth()->user()->role?->slug === 'superadmin')
        <script>
            const employeeSearch = document.getElementById('employee_search');
            const employeeId = document.getElementById('user_id');
            const employeeOptions = document.getElementById('employee_options');
            const requestForm = employeeSearch.form;

            function syncEmployeeSelection() {
                const match = Array.from(employeeOptions.options)
                    .find(option => option.value === employeeSearch.value);

                employeeId.value = match?.dataset.userId ?? '';
                employeeSearch.setCustomValidity(
                    employeeId.value ? '' : 'Select an employee from the suggestions.'
                );
            }

            employeeSearch.addEventListener('input', syncEmployeeSelection);
            requestForm.addEventListener('submit', event => {
                syncEmployeeSelection();
                if (!employeeId.value) {
                    event.preventDefault();
                    employeeSearch.reportValidity();
                }
            });
        </script>
    @endif
</body>
</html>
