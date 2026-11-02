<?php

declare(strict_types=1);

namespace App\Actions\SubscriptionPayments;

use App\Data\SubscriptionPayments\ExportSubscriptionPaymentsData;
use App\Models\SubscriptionPayment;
use App\Services\Service;
use App\Support\CsvExport;

/**
 * The subscription-payment CSV, honouring the index's search and date range.
 *
 * `amount` is read with `getRawOriginal()` so the stored decimal string is
 * written unchanged. Headers and column order are identical to the previous
 * in-controller export.
 */
class ExportSubscriptionPayments extends Service
{
    public function handle(ExportSubscriptionPaymentsData $data): CsvExport
    {
        $query = SubscriptionPayment::query()
            ->filtered([
                'search' => $data->search,
                'from' => $data->from,
                'to' => $data->to,
            ])
            ->orderByDesc('received_on')
            ->orderBy('id');

        if ($data->scope === 'page') {
            $query->forPage($data->page, 15);
        }

        $rows = $query->get()->map(fn (SubscriptionPayment $payment): array => [
            $payment->received_on?->format('Y-m-d') ?? '',
            $payment->organization?->name ?? '',
            $payment->plan?->name ?? '',
            $payment->getRawOriginal('amount'),
            $payment->currency,
            $payment->method ?? '',
            $payment->reference ?? '',
            $payment->notes ?? '',
        ]);

        return CsvExport::fromRows(
            'subscription-payments.csv',
            ['Received on', 'Organization', 'Plan', 'Amount', 'Currency', 'Method', 'Reference', 'Notes'],
            $rows,
        );
    }
}
