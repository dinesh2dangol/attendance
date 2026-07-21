<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <style>
        body { font-family: sans-serif; background: #f8fafc; color: #111827; }
        .container { max-width: 420px; margin: 4rem auto; padding: 2rem; background: white; border-radius: 0.75rem; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); }
        .field { margin-bottom: 1rem; }
        label { display: block; font-weight: 600; margin-bottom: 0.5rem; }
        input { width: 100%; padding: 0.75rem 0.9rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
        button { width: 100%; padding: 0.85rem; border: none; background: #111827; color: white; border-radius: 0.5rem; cursor: pointer; }
        .error { margin-bottom: 1rem; color: #b91c1c; }
        .small { font-size: 0.9rem; color: #6b7280; }
        .link { color: #2563eb; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Register</h1>

        @if ($errors->any())
            <div class="error">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div class="field">
                <label for="name">Name</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus>
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm Password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required>
            </div>

            <button type="submit">Register</button>
        </form>

        <p class="small">Already have an account? <a class="link" href="{{ route('login') }}">Log in</a></p>
    </div>
</body>
</html>
