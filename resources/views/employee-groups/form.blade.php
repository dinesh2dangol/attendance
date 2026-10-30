<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ isset($employeeGroup) ? 'Edit' : 'Create' }} Employee Group</title>
    <style>
        body { font-family:sans-serif; background:#f8fafc; color:#111827; }
        .container { max-width:760px; margin:3rem auto; padding:2rem; background:white; border-radius:.75rem; box-shadow:0 10px 30px rgba(15,23,42,.08); }
        .field { display:flex; flex-direction:column; gap:.5rem; margin-bottom:1rem; }
        input,select { padding:.65rem; border:1px solid #d1d5db; border-radius:.5rem; }
        .members { max-height:20rem; overflow:auto; border:1px solid #d1d5db; border-radius:.5rem; padding:.5rem; }
        .member { display:flex; align-items:center; gap:.5rem; padding:.35rem; }
        .member[hidden] { display:none; }
        .member input { padding:0; }
        .button { display:inline-block; padding:.65rem .9rem; border:0; border-radius:.5rem; background:#111827; color:white; text-decoration:none; cursor:pointer; }
        .secondary { background:#e2e8f0; color:#111827; }
        .errors { color:#b91c1c; }
        @media(max-width:700px) { .container { margin:1rem; padding:1rem; } }
    </style>
</head>
<body>
    <main class="container">
        <h1>{{ isset($employeeGroup) ? 'Edit' : 'Create' }} Employee Group</h1>
        @if ($errors->any())
            <ul class="errors">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        @endif

        <form method="POST" action="{{ isset($employeeGroup) ? route('employee-groups.update', $employeeGroup) : route('employee-groups.store') }}">
            @csrf
            @if (isset($employeeGroup)) @method('PUT') @endif
            <div class="field">
                <label for="name">Group name</label>
                <input id="name" name="name" maxlength="100" value="{{ old('name', $employeeGroup->name ?? '') }}" required>
            </div>
            <div class="field">
                <label for="supervisor_employee_id">Supervisor</label>
                @php
                    $selectedSupervisorId = old('supervisor_employee_id', $employeeGroup->supervisor_employee_id ?? '');
                    $selectedSupervisor = $supervisors->firstWhere('id', (int) $selectedSupervisorId);
                @endphp
                <input
                    id="supervisor_search"
                    type="search"
                    list="supervisor_suggestions"
                    value="{{ $selectedSupervisor ? $selectedSupervisor->employee_name . ' (' . $selectedSupervisor->account?->email . ')' : '' }}"
                    placeholder="Type a name or email to find a supervisor"
                    autocomplete="off"
                    required
                >
                <input id="supervisor_employee_id" name="supervisor_employee_id" type="hidden" value="{{ $selectedSupervisorId }}">
                <datalist id="supervisor_suggestions">
                    @foreach ($supervisors as $supervisor)
                        <option value="{{ $supervisor->employee_name }} ({{ $supervisor->account?->email }})" data-id="{{ $supervisor->id }}">
                        </option>
                    @endforeach
                </datalist>
                @if ($supervisors->isEmpty())
                    <small>No eligible supervisor accounts. Create an employee login first.</small>
                @else
                    <small>Type a name or email, then select a matching suggestion.</small>
                @endif
            </div>
            <div class="field">
                <label>Regular employees</label>
                <input id="member_search" type="search" list="member_suggestions" placeholder="Type a name or email to filter employees" autocomplete="off">
                <datalist id="member_suggestions">
                    @foreach ($members as $member)
                        <option value="{{ $member->employee_name }} ({{ $member->account?->email }})"></option>
                    @endforeach
                </datalist>
                <div class="members" id="members">
                    @foreach ($members as $member)
                        <label class="member" data-name="{{ strtolower($member->employee_name . ' ' . $member->account?->email) }}">
                            <input type="checkbox" name="members[]" value="{{ $member->id }}"
                                @checked(in_array((string) $member->id, array_map('strval', old('members', isset($employeeGroup) ? $employeeGroup->members->modelKeys() : [])), true))>
                            <span>{{ $member->employee_name }} ({{ $member->account?->email }})</span>
                        </label>
                    @endforeach
                    @if ($members->isEmpty())
                        <p>No regular employee accounts found.</p>
                    @endif
                </div>
            </div>
            <button class="button" type="submit">Save Group</button>
            <a class="button secondary" href="{{ route('employee-groups.index') }}">Cancel</a>
        </form>
    </main>
    <script>
        const memberSearch = document.getElementById('member_search');
        const supervisorSearch = document.getElementById('supervisor_search');
        const supervisorId = document.getElementById('supervisor_employee_id');
        const supervisorOptions = Array.from(document.querySelectorAll('#supervisor_suggestions option'));

        const updateSupervisor = () => {
            const selected = supervisorOptions.find(option => option.value === supervisorSearch.value);
            supervisorId.value = selected?.dataset.id ?? '';
            supervisorSearch.setCustomValidity(
                supervisorSearch.value !== '' && !selected
                    ? 'Select a supervisor from the suggestions.'
                    : ''
            );
        };

        supervisorSearch.addEventListener('input', updateSupervisor);
        supervisorSearch.form.addEventListener('submit', updateSupervisor);
        memberSearch.addEventListener('input', () => {
            const query = memberSearch.value.trim().toLowerCase();
            document.querySelectorAll('#members .member').forEach(row => {
                row.hidden = !row.dataset.name.includes(query);
            });
        });
    </script>
</body>
</html>
