<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Models\AiSetting;
use App\Services\Service;
use Illuminate\Database\Eloquent\Model;

/**
 * Removes the AI credential for a morph owner — an organization or a user.
 *
 * One action for both scopes because the operation is identical; only the
 * owner differs. The owner-only rule for organizations stays in the request,
 * where the tenancy context is available.
 */
class DeleteAiSetting extends Service
{
    public function handle(Model $owner): void
    {
        $this->transaction(function () use ($owner): void {
            AiSetting::query()
                ->whereMorphedTo('owner', $owner)
                ->delete();
        });
    }
}
