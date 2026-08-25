<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\OtpVerifyRequest;
use App\Models\User;
use App\Support\AuthLanding;
use App\Support\InertiaRedirect;
use App\Support\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
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

    public function verify(OtpVerifyRequest $request, OtpService $otpService): RedirectResponse|HttpResponse
    {
        $email = $request->session()->get('login.email');

        if (! $email) {
            return redirect()->route('login');
        }

        $validated = $request->validated();

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

        activity()->inLog('auth')
            ->causedBy($user)
            ->event('otp.verified')
            ->log('Verified one-time code');

        $landing = $user->isOnboarded()
            ? AuthLanding::for($user, $request)
            : route('onboarding.show');

        $intended = $request->session()->get('url.intended');

        return InertiaRedirect::to($intended ?? $landing, $request);
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
