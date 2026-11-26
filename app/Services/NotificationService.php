<?php

namespace App\Services;

use App\Mail\BillingReminderMail;
use App\Mail\OperationalNoticeMail;
use App\Mail\RentReminderMail;
use App\Models\DepositRefund;
use App\Models\Lease;
use App\Models\Organization;
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

    /**
     * One operational status update (move-out and deposit traffic) to a
     * single recipient, routed by their channel preference like every
     * other notifier here. Never throws: a failed notification must not
     * roll back the business write that triggered it.
     */
    public function sendOperationalNotice(User $recipient, string $subject, string $body, ?string $url = null): bool
    {
        try {
            if ($recipient->prefersSms() && $recipient->phone) {
                $result = $this->sms->send($recipient->phone, $body);

                if ($this->sms->succeeded($result)) {
                    return true;
                }

                logger()->warning('Operational SMS failed, falling back to email.', [
                    'user_id' => $recipient->id,
                    'subject' => $subject,
                ]);
            }

            Mail::to($recipient->email)->send(new OperationalNoticeMail($subject, $body, $url));

            return true;
        } catch (\Throwable $exception) {
            logger()->warning('Operational notice failed.', [
                'user_id' => $recipient->id,
                'subject' => $subject,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Fan-out to every owner of the organization. Owners always exist,
     * unlike delegated staff, so operational news has a guaranteed
     * audience. Best effort per recipient: one bad address never
     * silences the rest.
     */
    public function notifyOwners(Organization $organization, string $subject, string $body, ?string $url = null): void
    {
        $owners = $organization->users()
            ->wherePivot('is_owner', true)
            ->wherePivot('status', 'active')
            ->get();

        foreach ($owners as $owner) {
            $this->sendOperationalNotice($owner, $subject, $body, $url);
        }
    }

    /**
     * Reach the tenant behind a refund: their account when they have one,
     * otherwise their person record's email or phone, mirroring the rent
     * reminder fallback. Someone without any contact simply cannot be
     * reached, which the caller treats as delivered-nowhere, not an error.
     */
    public function notifyTenantForRefund(DepositRefund $refund, string $subject, string $body, ?string $url = null): bool
    {
        $refund->loadMissing(['lease.person', 'requester']);

        if ($refund->requester instanceof User) {
            return $this->sendOperationalNotice($refund->requester, $subject, $body, $url);
        }

        $person = $refund->lease?->person;

        $account = $person !== null
            ? User::query()->where('person_id', $person->id)->first()
            : null;

        if ($account !== null) {
            return $this->sendOperationalNotice($account, $subject, $body, $url);
        }

        try {
            if ($person?->email) {
                Mail::to($person->email)->send(new OperationalNoticeMail($subject, $body, $url));

                return true;
            }

            if ($person?->phone) {
                return $this->sendSmsTo($person->phone, $body);
            }
        } catch (\Throwable $exception) {
            logger()->warning('Operational notice to tenant failed.', [
                'lease_id' => $refund->lease_id,
                'subject' => $subject,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        return false;
    }

    /**
     * Reach the tenant behind a lease, for move-out traffic where the
     * notice itself names the requester. Same account-first, contact-
     * fallback order as refunds.
     */
    public function notifyTenantForLease(Lease $lease, ?User $knownUser, string $subject, string $body, ?string $url = null): bool
    {
        $lease->loadMissing('person');

        $account = $knownUser
            ?? ($lease->person !== null
                ? User::query()->where('person_id', $lease->person->id)->first()
                : null);

        if ($account instanceof User) {
            return $this->sendOperationalNotice($account, $subject, $body, $url);
        }

        return false;
    }
}
