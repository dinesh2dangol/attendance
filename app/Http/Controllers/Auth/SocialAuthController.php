<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirectToProvider(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleProviderCallback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors(['oauth' => 'Unable to authenticate using Google.']);
        }

        if (! $googleUser || ! $googleUser->getEmail()) {
            return redirect()->route('login')->withErrors(['oauth' => 'No email returned from Google.']);
        }

        $user = User::where('email', $googleUser->getEmail())->first();

        if (! $user) {
            $user = new User();
            $user->name = $googleUser->getName() ?? $googleUser->getNickname() ?? $googleUser->getEmail();
            $user->email = $googleUser->getEmail();
            $user->password = Hash::make(Str::random(24));
            $user->role_id = Role::where('slug', 'employee')->value('id') ?: null;
            $user->save();
        }

        Auth::login($user, true);

        return $user->role?->slug === 'employee'
            ? redirect()->route('employee.attendance.self')
            : redirect()->route('dashboard');
    }
}
