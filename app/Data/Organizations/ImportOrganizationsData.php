<?php

declare(strict_types=1);

namespace App\Data\Organizations;

use App\Models\User;
use Illuminate\Http\UploadedFile;

final readonly class ImportOrganizationsData
{
    public function __construct(
        public UploadedFile $file,
        public ?int $planId,
        public ?User $actor,
    ) {}
}
