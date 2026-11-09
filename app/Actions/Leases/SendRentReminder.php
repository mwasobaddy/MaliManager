<?php

namespace App\Actions\Leases;

use App\Mail\RentReminderMail;
use App\Models\Lease;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\RentArrearsService;
use App\Services\Service;
use Illuminate\Support\Facades\Mail;

/**
 * Nudges an overdue lease's occupant about the outstanding balance, on
 * the occupant's own channel preference. Throttled to one reminder per
 * lease per day so bulk runs and repeated clicks cannot spam tenants.
 * Account holders get preference routing; persons without an account
 * fall back to their email, or SMS when only a phone exists.
 */
class SendRentReminder extends Service
{
    public function __construct(
        private RentArrearsService $arrears,
        private NotificationService $notifications,
    ) {}

    /**
     * @return array{lease_id: int, channel: string, outstanding: string}
     */
    public function handle(Lease $lease, User $actor): array
    {
        abort_unless($lease->isActive(), 422, 'This lease is not active.');

        if ($lease->last_reminded_at !== null && $lease->last_reminded_at->greaterThan(now()->subDay())) {
            throw new \DomainException('A reminder was already sent for this lease in the last 24 hours.');
        }

        $balance = $this->arrears->balanceFor($lease->loadMissing(['person', 'unit', 'property', 'organization']));

        if (! $balance['overdue']) {
            throw new \DomainException('This lease has no outstanding balance.');
        }

        $person = $balance['lease']->person;
        $body = $this->message($balance);
        $channel = $this->recipientChannel($balance['lease']);

        if ($channel === null) {
            throw new \DomainException('This occupant has no email or phone to remind.');
        }

        $recipient = $person !== null
            ? User::query()->where('person_id', $person->id)->first()
            : null;

        if ($recipient !== null) {
            $this->notifications->sendRentReminder($recipient, $body);
        } elseif ($channel === 'email' && $person?->email) {
            Mail::to($person->email)->send(new RentReminderMail(
                $balance['lease']->property?->name ?? '',
                $body,
            ));
        } elseif ($channel === 'sms' && $person?->phone) {
            $this->notifications->sendSmsTo($person->phone, $body);
        }

        $this->save($lease->fill(['last_reminded_at' => now()]));

        activity()->inLog('leases')
            ->causedBy($actor)
            ->performedOn($lease)
            ->event('rent.reminder.sent')
            ->withProperties(['channel' => $channel, 'outstanding' => $balance['outstanding']])
            ->log("Sent a rent reminder ({$channel})");

        return [
            'lease_id' => $lease->id,
            'channel' => $channel,
            'outstanding' => $balance['outstanding'],
        ];
    }

    /**
     * The channel a reminder to this lease would use, or null when the
     * occupant is unreachable. Shared by sending and by the AI broadcast
     * draft so previews never disagree with delivery.
     */
    public function recipientChannel(Lease $lease): ?string
    {
        $lease->loadMissing('person');
        $person = $lease->person;

        $recipient = $person !== null
            ? User::query()->where('person_id', $person->id)->first()
            : null;

        if ($recipient !== null) {
            return $recipient->prefersSms() && $recipient->phone ? 'sms' : 'email';
        }

        if ($person?->email) {
            return 'email';
        }

        if ($person?->phone) {
            return 'sms';
        }

        return null;
    }

    /**
     * @param  array{lease: Lease, outstanding: string, latest_due_date: ?string}  $balance
     */
    private function message(array $balance): string
    {
        $lease = $balance['lease'];
        $name = trim(($lease->person?->first_name ?? '').' '.($lease->person?->last_name ?? '')) ?: 'there';
        $unit = $lease->unit?->name ?? '';
        $property = $lease->property?->name ?? '';
        $currency = $lease->currency ?? 'KES';

        $message = "Hello {$name}, your {$currency} {$balance['outstanding']} rent for {$unit}, {$property} was due on {$balance['latest_due_date']}. Please pay at your earliest convenience.";

        $details = $lease->organization?->paymentDetailsSummary();

        if ($details !== null && $details !== '') {
            $message .= " How to pay: {$details}";
        }

        return $message;
    }
}
