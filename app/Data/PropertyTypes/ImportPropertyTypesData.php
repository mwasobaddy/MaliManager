<?php

declare(strict_types=1);

namespace App\Data\PropertyTypes;

use Illuminate\Http\UploadedFile;

final readonly class ImportPropertyTypesData
{
    public function __construct(
        public UploadedFile $file,
    ) {}
}
