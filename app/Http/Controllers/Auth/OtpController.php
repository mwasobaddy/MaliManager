<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OtpController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $email = $request->session()->get('login.email');

        if (! $email) {
            return redirect()->route('login');
        }

        return Inertia::render('auth/otp', [
            'email' => $email,
        ]);
    }

    public function verify(Request $request, OtpService $otpService): RedirectResponse
    {
        $email = $request->session()->get('login.email');

        if (! $email) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $user = User::where('email', $email)->first();

        if (! $user || ! $otpService->verify($user, $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => 'The code you entered is invalid or has expired.',
            ]);
        }

        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $request->session()->forget('login.email');

        Auth::login($user);

        return redirect()->intended(
            $user->isOnboarded() ? route('dashboard') : route('onboarding.show'),
        );
    }

    public function resend(Request $request, OtpService $otpService): RedirectResponse
    {
        $email = $request->session()->get('login.email');

        if (! $email) {
            return redirect()->route('login');
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return redirect()->route('login');
        }

        $otpService->issue($user);

        return back()->with('status', 'A new code has been sent to your email.');
    }
}
