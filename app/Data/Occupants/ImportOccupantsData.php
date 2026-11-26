<?php

declare(strict_types=1);

namespace App\Data\Occupants;

use App\Models\Property;
use App\Models\User;
use Illuminate\Http\UploadedFile;

final readonly class ImportOccupantsData
{
    public function __construct(
        public UploadedFile $file,
        public User $actor,
        public Property $property,
    ) {}
}
