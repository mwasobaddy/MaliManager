<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Whether an actor may delete a given row, mirroring the trash tier
 * everywhere: platform admins and organization owners may delete any
 * row in scope, everyone else only rows they created. Bulk delete
 * (`BulkSoftDelete::inScope`) enforces the same rule, so a row the
 * index refuses to delete can never be bulk-deleted either.
 *
 * Rows without a creator (`created_by` null — legacy imports) are
 * owner/admin-only: nobody else can claim them.
 */
final class DeletionTier
{
    public static function allows(User $actor, ?Organization $organization, Model $model): bool
    {
        if ($actor->isPlatformAdmin()) {
            return true;
        }

        if ($organization !== null && $actor->isOwnerOf($organization)) {
            return true;
        }

        $creator = (int) ($model->getAttribute('created_by') ?? 0);

        return $creator !== 0 && $creator === (int) $actor->getKey();
    }
}
