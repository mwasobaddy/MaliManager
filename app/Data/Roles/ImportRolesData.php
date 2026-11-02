<?php

declare(strict_types=1);

namespace App\Data\Roles;

use Illuminate\Http\UploadedFile;

final readonly class ImportRolesData
{
    public function __construct(
        public UploadedFile $file,
    ) {}
}
