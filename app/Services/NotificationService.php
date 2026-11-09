<?php

namespace App\Services;

use App\Mail\BillingReminderMail;
use App\Mail\RentReminderMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Single front door for user notifications. Every notifier — the billing
 * cron today, rent reminders tomorrow — routes through the RECIPIENT's
 * stored channel preference, never the sender's. SMS needs the
 * recipient's phone; without one the message falls back to email with a
 * log line, so a missing number can never swallow a reminder.
 */
class NotificationService extends Service
{
    public function __construct(private SmsClient $sms) {}

    /**
     * Deliver a billing reminder about an organization to its owner.
     */
    public function sendBillingReminder(User $recipient, string $organizationName, string $planName, string $body): bool
    {
        if ($recipient->prefersSms() && $recipient->phone) {
            $result = $this->sms->send($recipient->phone, $body);

            if ($this->sms->succeeded($result)) {
                return true;
            }

            logger()->warning('Billing SMS failed, falling back to email.', [
                'user_id' => $recipient->id,
            ]);
        }

        Mail::to($recipient->email)->send(new BillingReminderMail($organizationName, $planName, $body));

        return true;
    }

    /**
     * Deliver a rent reminder to a tenant on their own channel preference.
     */
    public function sendRentReminder(User $recipient, string $body): bool
    {
        if ($recipient->prefersSms() && $recipient->phone) {
            $result = $this->sms->send($recipient->phone, $body);

            if ($this->sms->succeeded($result)) {
                return true;
            }

            logger()->warning('Rent SMS failed, falling back to email.', [
                'user_id' => $recipient->id,
            ]);
        }

        Mail::to($recipient->email)->send(new RentReminderMail(
            propertyName: '',
            body: $body,
        ));

        return true;
    }

    /**
     * Direct SMS for persons without an account (no channel preference
     * exists to route by). Throws on gateway failure — the caller
     * decides the fallback.
     */
    public function sendSmsTo(string $phone, string $body): bool
    {
        $result = $this->sms->send($phone, $body);

        if (! $this->sms->succeeded($result)) {
            throw new \DomainException('The SMS could not be delivered. Please try again.');
        }

        return true;
    }
}
