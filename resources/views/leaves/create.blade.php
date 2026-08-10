<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Leave</title>
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
        <h1>Add Leave</h1>

        @if ($errors->any())
            <div style="color:red;margin-bottom:1rem">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('leaves.store') }}">
            @csrf
            <div class="field">
                <label for="employee_id">Employee (optional)</label>
                <select name="user_id" id="user_id">
                    <option value="">-- Select --</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->user_id }}" {{ (string)($selectedUserId ?? request('user_id')) === (string)$emp->user_id ? 'selected' : '' }}>
                            {{ $emp->employee_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="leave_date">Date</label>
                <input id="leave_date" name="leave_date" type="date" required />
            </div>

            <div class="field">
                <label for="leave_type">Type</label>
                <select id="leave_type" name="leave_type" required>
                    <option value="">-- Select type --</option>
                    @foreach($leaveTypes as $type)
                        <option value="{{ $type }}" {{ request('leave_type') === $type ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="leave_description">Description</label>
                <textarea id="leave_description" name="leave_description" rows="4"></textarea>
            </div>

            <div style="display:flex;gap:0.5rem">
                <button class="button" type="submit">Save</button>
                <a href="{{ route('leaves.index') }}" style="align-self:center;">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
