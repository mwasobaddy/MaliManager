<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\LoginIdentifier;
use App\Support\OtpService;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function store(LoginRequest $request, OtpService $otpService): RedirectResponse
    {
        $validated = $request->validated();
        $identifier = trim((string) $validated['identifier']);

        if (filter_var(strtolower($identifier), FILTER_VALIDATE_EMAIL) !== false) {
            $email = strtolower($identifier);
            $user = User::where('email', $email)->first();

            if (! $user) {
                $user = User::create([
                    'name' => ucfirst(Str::before($email, '@')),
                    'email' => $email,
                    'status' => 'active',
                ]);
            }

            $channel = OtpService::CHANNEL_EMAIL;
            $contact = $email;
        } else {
            $phone = PhoneNumber::normalize($identifier);
            $user = $phone !== null ? LoginIdentifier::findUser($identifier) : null;

            if ($user === null) {
                // No mailbox to attach to a phone signup, and the address
                // column cannot be null: use a non-routable placeholder that
                // is replaced the moment a real email is known.
                $placeholder = $phone !== null
                    ? ltrim($phone, '+').'@phone.invalid'
                    : $identifier.'@phone.invalid';

                $user = User::create([
                    'name' => $phone ?? $identifier,
                    'email' => $placeholder,
                    'phone' => $phone,
                    'notification_channel' => 'sms',
                    'status' => 'active',
                ]);
            }

            $channel = OtpService::CHANNEL_SMS;
            $contact = $user->phone;
        }

        if ($user->status !== 'active') {
            activity()->inLog('auth')
                ->causedBy($user)
                ->performedOn($user)
                ->event('login.suspended')
                ->log('Blocked sign-in for a suspended account');

            throw ValidationException::withMessages([
                'identifier' => 'This account has been suspended.',
            ]);
        }

        $otpService->issue($user, $channel, is_string($contact) ? $contact : null);

        activity()->inLog('auth')
            ->causedBy($user)
            ->performedOn($user)
            ->event('otp.issued')
            ->log('Issued one-time code');

        $request->session()->put([
            'login.identifier' => $channel === OtpService::CHANNEL_EMAIL ? $user->email : $user->phone,
            'login.channel' => $channel,
            'login.remember' => (bool) ($validated['remember'] ?? false),
        ]);

        return redirect()->route('login.otp');
    }
}
