<?php

declare(strict_types=1);

namespace App\Data\PlatformExpenses;

use App\Models\User;
use Illuminate\Http\UploadedFile;

final readonly class ImportPlatformExpensesData
{
    public function __construct(
        public UploadedFile $file,
        public ?User $actor,
    ) {}
}
