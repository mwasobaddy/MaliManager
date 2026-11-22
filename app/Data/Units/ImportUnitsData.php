<?php

declare(strict_types=1);

namespace App\Data\Units;

use App\Models\User;
use Illuminate\Http\UploadedFile;

final readonly class ImportUnitsData
{
    public function __construct(
        public UploadedFile $file,
        public User $actor,
    ) {}
}
