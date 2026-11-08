<?php

declare(strict_types=1);

namespace App\Actions\Searches;

use App\Models\SavedSearch;
use App\Services\Service;

/**
 * Removes one saved search. Ownership is asserted by the Form Request; the
 * action only deletes.
 */
class DeleteSavedSearch extends Service
{
    public function handle(SavedSearch $search): void
    {
        $this->delete($search);
    }
}
