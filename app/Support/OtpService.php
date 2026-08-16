<?php

namespace App\Support;

use App\Mail\LoginOtpMail;
use App\Models\LoginOtp;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Issues and verifies one-time passwords for email login.
 *
 * A fresh OTP is generated only when the previous one has been used or
 * expired; otherwise the existing code is re-sent.
 */
class OtpService
{
    public const TTL_MINUTES = 10;

    public function issue(User $user): string
    {
        $otp = $this->latestUsable($user);

        if (! $otp) {
            $otp = LoginOtp::create([
                'user_id' => $user->id,
                'code' => (string) random_int(100000, 999999),
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            ]);
        }

        Mail::to($user->email)->send(new LoginOtpMail($otp->code));

        return $otp->code;
    }

    public function verify(User $user, string $code): bool
    {
        $otp = $this->latestUsable($user);

        if (! $otp || ! hash_equals($otp->code, trim($code))) {
            return false;
        }

        $otp->update(['used_at' => now()]);

        return true;
    }

    private function latestUsable(User $user): ?LoginOtp
    {
        return LoginOtp::where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }
}
