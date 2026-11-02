<?php

declare(strict_types=1);

namespace App\Data\SubscriptionPayments;

use App\Models\User;
use Illuminate\Http\UploadedFile;

final readonly class ImportSubscriptionPaymentsData
{
    public function __construct(
        public UploadedFile $file,
        public ?User $actor,
    ) {}
}
