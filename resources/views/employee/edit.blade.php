<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee</title>
    <style>
        body { font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, sans-serif; background: #f8fafc; color: #0f172a; }
        .container { max-width: 680px; margin: 3rem auto; padding: 2rem; background: white; border-radius: 1rem; box-shadow: 0 20px 50px rgba(15, 23, 42, 0.12); }
        h1 { margin-bottom: 0.5rem; font-size: 1.8rem; }
        p { margin-top: 0.25rem; color: #64748b; }
        form { display: grid; gap: 1rem; margin-top: 1.5rem; }
        label { display: grid; gap: 0.5rem; font-weight: 600; color: #334155; }
        input, select { width: 100%; padding: 0.85rem 1rem; border-radius: 0.75rem; border: 1px solid #cbd5e1; background: #f8fafc; color: #0f172a; }
        input[type="checkbox"] { width: auto; }
        .actions { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 1rem; }
        .button, .button-secondary { display: inline-flex; align-items: center; justify-content: center; padding: 0.85rem 1.25rem; border: none; border-radius: 0.75rem; cursor: pointer; font-weight: 600; }
        .button { background: #1d4ed8; color: white; }
        .button-secondary { background: #e2e8f0; color: #0f172a; text-decoration: none; }
        .errors { padding: 1rem; background: #fee2e2; border: 1px solid #fecaca; border-radius: 0.75rem; color: #991b1b; }
        .success { padding: 1rem; background: #d1fae5; border: 1px solid #6ee7b7; border-radius: 0.75rem; color: #047857; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Edit Employee</h1>
        <p>Update details for {{ $employee->employee_name }}</p>

        @if ($errors->any())
            <div class="errors">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('success'))
            <div class="success">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('employees.update', $employee) }}">
            @csrf
            @method('PUT')

            <label>
                Name
                <input type="text" name="employee_name" value="{{ old('employee_name', $employee->employee_name) }}" required />
            </label>

            <label>
                Join Date (ENG)
                <input type="datetime-local" name="join_date_eng" value="{{ old('join_date_eng', optional($employee->join_date_eng)->format('Y-m-d\TH:i')) }}" />
            </label>

            <label>
                Join Date (NPT)
                <input type="text" name="join_date_npt" value="{{ old('join_date_npt', $employee->join_date_npt) }}" />
            </label>

            <label>
                Status
                <select name="status">
                    <option value="" {{ old('status', $employee->status) === '' || old('status', $employee->status) === null ? 'selected' : '' }}>Select status</option>
                    <option value="0" {{ old('status', $employee->status) == '0' ? 'selected' : '' }}>Inactive</option>
                    <option value="1" {{ old('status', $employee->status) == '1' ? 'selected' : '' }}>Active</option>
                    <option value="2" {{ old('status', $employee->status) == '2' ? 'selected' : '' }}>Disabled</option>
                </select>
            </label>

            <label>
                Salary
                <input type="number" step="0.01" name="salary" value="{{ old('salary', $employee->salary) }}" />
            </label>

            <label>
                Working Hours
                <input type="number" step="0.01" name="working_hours" value="{{ old('working_hours', $employee->working_hours) }}" />
            </label>

            <label>
                Part Time
                <select name="part_time">
                    <option value="" {{ old('part_time', $employee->part_time) === null ? 'selected' : '' }}>Select</option>
                    <option value="1" {{ old('part_time', $employee->part_time) == 1 ? 'selected' : '' }}>Yes</option>
                    <option value="0" {{ old('part_time', $employee->part_time) == 0 ? 'selected' : '' }}>No</option>
                </select>
            </label>

            <label>
                Department
                <select name="department_id">
                    <option value="">Select department</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->department_id }}" {{ old('department_id', $employee->department_id) == $dept->department_id ? 'selected' : '' }}>{{ $dept->department_name }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                Gender
                <select name="gender">
                    <option value="">Select gender</option>
                    <option value="male" {{ strtolower(old('gender', $employee->gender)) === 'male' ? 'selected' : '' }}>Male</option>
                    <option value="female" {{ strtolower(old('gender', $employee->gender)) === 'female' ? 'selected' : '' }}>Female</option>
                    <option value="other" {{ strtolower(old('gender', $employee->gender)) === 'other' ? 'selected' : '' }}>Other</option>
                </select>
            </label>

            <div class="actions">
                <button class="button" type="submit">Save changes</button>
                <a class="button-secondary" href="{{ route('dashboard') }}">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
