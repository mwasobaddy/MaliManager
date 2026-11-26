<?php

namespace App\Support;

use App\Mail\LoginOtpMail;
use App\Models\LoginOtp;
use App\Models\User;
use App\Services\SmsClient;
use Illuminate\Support\Facades\Mail;

/**
 * Issues and verifies one-time passwords for login, over email or SMS.
 *
 * A fresh OTP is generated only when the previous one has been used or
 * expired; otherwise the existing code is re-sent. SMS goes through the
 * shared SmsClient; when no gateway credentials are configured (local
 * development, CI) the code is logged instead of thrown away, mirroring
 * the mail log driver.
 */
class OtpService
{
    public const TTL_MINUTES = 10;

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_SMS = 'sms';

    public function __construct(private SmsClient $sms) {}

    public function issue(User $user, string $channel = self::CHANNEL_EMAIL, ?string $to = null): string
    {
        $otp = $this->latestUsable($user);

        if (! $otp) {
            $otp = LoginOtp::create([
                'user_id' => $user->id,
                'code' => (string) random_int(100000, 999999),
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            ]);
        }

        if ($channel === self::CHANNEL_SMS) {
            $this->sendSms($to ?? $user->phone ?? '', $otp->code);
        } else {
            Mail::to($to ?? $user->email)->send(new LoginOtpMail($otp->code));
        }

        return $otp->code;
    }

    /**
     * @return array{status: string, data: mixed}|null null when the gateway
     *                                                 is unconfigured and the code was only logged (local dev, CI).
     */
    private function sendSms(string $to, string $code): ?array
    {
        if ($to === '' || (string) config('services.africastalking.key') === '') {
            logger()->info('OTP SMS not sent: no recipient or no gateway configured.', ['to' => $to, 'code' => $code]);

            return null;
        }

        return $this->sms->send(PhoneNumber::normalize($to) ?? $to, "Your MaliManager login code is {$code}");
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
