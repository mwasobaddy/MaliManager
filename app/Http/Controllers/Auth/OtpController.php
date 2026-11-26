<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\OtpRequest;
use App\Http\Requests\Auth\OtpVerifyRequest;
use App\Support\AuthLanding;
use App\Support\InertiaRedirect;
use App\Support\LoginIdentifier;
use App\Support\OtpService;
use App\Support\PhoneNumber;
use App\Support\TwoFactorRequirement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OtpController extends Controller
{
    public function show(OtpRequest $request): Response|RedirectResponse
    {
        $identifier = $request->session()->get('login.identifier');
        $channel = $request->session()->get('login.channel', OtpService::CHANNEL_EMAIL);

        if (! is_string($identifier) || $identifier === '') {
            return redirect()->route('login');
        }

        return Inertia::render('auth/otp', [
            'identifier' => $identifier,
            'channel' => $channel,
            'masked' => self::mask($identifier, (string) $channel),
            // The page predates channels: existing bookmarks and tests read
            // `email`. Keep it populated for email logins so nothing that
            // renders it breaks; phone logins leave it null.
            'email' => $channel === OtpService::CHANNEL_EMAIL ? $identifier : null,
        ]);
    }

    public function verify(OtpVerifyRequest $request, OtpService $otpService): RedirectResponse|HttpResponse
    {
        $identifier = $request->session()->get('login.identifier');

        if (! is_string($identifier) || $identifier === '') {
            return redirect()->route('login');
        }

        $validated = $request->validated();

        $user = LoginIdentifier::findUser($identifier);

        if (! $user || ! $otpService->verify($user, $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => 'The code you entered is invalid or has expired.',
            ]);
        }

        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $request->session()->forget('login.identifier');
        $remember = (bool) $request->session()->pull('login.remember', false);

        Auth::login($user, $remember);

        activity()->inLog('auth')
            ->causedBy($user)
            ->performedOn($user)
            ->event('otp.verified')
            ->log('Verified one-time code');

        if ($enforcement = TwoFactorRequirement::for($user)) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => $enforcement['message']]);

            return InertiaRedirect::to($enforcement['url'], $request);
        }

        $landing = $user->isOnboarded()
            ? AuthLanding::for($user, $request)
            : route('onboarding.show');

        $intended = $request->session()->get('url.intended');

        return InertiaRedirect::to($intended ?? $landing, $request);
    }

    public function resend(OtpRequest $request, OtpService $otpService): RedirectResponse
    {
        $identifier = $request->session()->get('login.identifier');
        $channel = $request->session()->get('login.channel', OtpService::CHANNEL_EMAIL);

        if (! is_string($identifier) || $identifier === '') {
            return redirect()->route('login');
        }

        $user = LoginIdentifier::findUser($identifier);

        if (! $user) {
            return redirect()->route('login');
        }

        $contact = $channel === OtpService::CHANNEL_SMS
            ? $user->phone
            : $user->email;

        $otpService->issue($user, (string) $channel, $contact);

        activity()->inLog('auth')
            ->causedBy($user)
            ->performedOn($user)
            ->event('otp.issued')
            ->withProperties(['resent' => true])
            ->log('Re-issued one-time code');

        return back()->with('status', 'A new code has been sent.');
    }

    /**
     * Shows just enough to confirm where the code went without leaking the
     * full address or number on a shared screen.
     */
    public static function mask(string $identifier, string $channel): string
    {
        if ($channel === OtpService::CHANNEL_SMS) {
            return PhoneNumber::mask($identifier);
        }

        $at = strpos($identifier, '@');

        if ($at === false || $at <= 0 || $at === strlen($identifier) - 1) {
            return $identifier;
        }

        return substr($identifier, 0, 1).'•••••'.substr($identifier, $at);
    }
}
