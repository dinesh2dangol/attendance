<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
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
        <h1>Log in</h1>

        @if ($errors->any())
            <div class="error">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>
            </div>

            <div class="field">
                <label><input type="checkbox" name="remember"> Remember me</label>
            </div>

            <button type="submit">Log in</button>
        </form>

        <div style="margin-top:1rem; text-align:center;">
            <p class="small">Or sign in with</p>
            <a href="{{ route('auth.google') }}" style="display:inline-flex; align-items:center; gap:0.5rem; padding:0.6rem 0.9rem; border-radius:0.5rem; border:1px solid #d1d5db; text-decoration:none; color:#111827; margin-top:0.5rem;">
                <svg aria-hidden="true" viewBox="0 0 48 48" style="width:18px; height:18px;" xmlns="http://www.w3.org/2000/svg">
                    <path fill="#4285F4" d="M43.6 24.5c0-1.4-.1-2.8-.4-4.1H24v7.8h11a9.4 9.4 0 0 1-4.1 6.2v5.1h6.6c3.9-3.6 6.1-8.8 6.1-15Z"/>
                    <path fill="#34A853" d="M24 44c5.5 0 10.1-1.8 13.5-4.9l-6.6-5.1c-1.8 1.2-4.1 2-6.9 2-5.3 0-9.9-3.6-11.5-8.4H5.7v5.3A20 20 0 0 0 24 44Z"/>
                    <path fill="#FBBC05" d="M12.5 27.6a12 12 0 0 1 0-7.2v-5.3H5.7a20 20 0 0 0 0 17.8l6.8-5.3Z"/>
                    <path fill="#EA4335" d="M24 12c3 0 5.7 1 7.8 3.1l5.8-5.8C34.1 6 29.5 4 24 4A20 20 0 0 0 5.7 15.1l6.8 5.3C14.1 15.6 18.7 12 24 12Z"/>
                </svg>
                <span>Login with Google</span>
            </a>
        </div>

    </div>
</body>
</html>
