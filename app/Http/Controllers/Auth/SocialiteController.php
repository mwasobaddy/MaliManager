<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuthLanding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

class SocialiteController extends Controller
{
    public function redirect(string $provider): RedirectResponse
    {
        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            Log::warning('OAuth callback failed', ['provider' => $provider, 'error' => $e->getMessage()]);

            return redirect()->route('login')->withErrors([
                'email' => 'Unable to sign in with '.ucfirst($provider).'. Please try again.',
            ]);
        }

        $user = $this->findOrCreateUser($provider, $socialUser);

        Auth::login($user);

        $landing = $user->isOnboarded()
            ? AuthLanding::for($user, $request)
            : route('onboarding.show');

        return redirect()->intended($landing);
    }

    private function findOrCreateUser(string $provider, SocialiteUser $socialUser): User
    {
        if (! $socialUser->getEmail()) {
            throw new \RuntimeException('OAuth provider did not return an email address.');
        }

        $user = User::where('email', $socialUser->getEmail())->first();

        if ($user) {
            return $user;
        }

        return User::create([
            'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? $socialUser->getEmail(),
            'email' => $socialUser->getEmail(),
            'password' => null,
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'status' => 'active',
        ]);
    }
}
