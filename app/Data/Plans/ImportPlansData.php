<?php

declare(strict_types=1);

namespace App\Data\Plans;

use App\Models\User;
use Illuminate\Http\UploadedFile;

final readonly class ImportPlansData
{
    public function __construct(
        public UploadedFile $file,
        public ?User $actor,
    ) {}
}
