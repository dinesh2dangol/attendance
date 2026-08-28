<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use League\OAuth2\Client\Provider\Google;

class GoogleOAuthController extends Controller
{
    public function redirect(Request $request)
    {
        $provider = new Google([
            'clientId'     => env('GOOGLE_CLIENT_ID'),
            'clientSecret' => env('GOOGLE_CLIENT_SECRET'),
            'redirectUri'  => env('GOOGLE_REDIRECT'),
        ]);

        $authUrl = $provider->getAuthorizationUrl([
            'scope' => ['email', 'profile'],
            // force account chooser so user can select a different Google account
            'prompt' => 'select_account',
            // request no consent prompt but allow refresh tokens if needed
            'access_type' => 'offline',
        ]);
        $request->session()->put('oauth2state', $provider->getState());

        return redirect()->away($authUrl);
    }

    public function callback(Request $request)
    {
        $sessionState = $request->session()->pull('oauth2state');
        $state = $request->query('state');

        if (empty($state) || $state !== $sessionState) {
            return redirect()->route('login')->withErrors(['oauth' => 'Invalid OAuth state']);
        }

        $provider = new Google([
            'clientId'     => env('GOOGLE_CLIENT_ID'),
            'clientSecret' => env('GOOGLE_CLIENT_SECRET'),
            'redirectUri'  => env('GOOGLE_REDIRECT'),
        ]);

        try {
            $token = $provider->getAccessToken('authorization_code', ['code' => $request->query('code')]);
            $owner = $provider->getResourceOwner($token);
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors(['oauth' => 'Unable to authenticate using Google.']);
        }

        $email = $owner->getEmail();
        if (! $email) {
            return redirect()->route('login')->withErrors(['oauth' => 'No email returned from Google.']);
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return redirect()->route('login')
                ->withErrors(['oauth' => 'No account exists for that Google email. Please sign in with another account.']);
        }

        Auth::login($user, true);

        return $user->role?->slug === 'employee'
            ? redirect()->route('employee.attendance.self')
            : redirect()->route('dashboard');
    }
}
