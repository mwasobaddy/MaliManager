<?php

declare(strict_types=1);

namespace App\Data\Users;

use App\Models\User;
use Illuminate\Http\UploadedFile;

final readonly class ImportUsersData
{
    public function __construct(
        public UploadedFile $file,
        public User $actor,
    ) {}
}
