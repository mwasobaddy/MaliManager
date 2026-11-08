<?php

declare(strict_types=1);

namespace App\Actions\Searches;

use App\Actions\Onboarding\Concerns\OnboardingHelpers;
use App\Models\SavedSearch;
use App\Models\User;
use App\Services\Service;

/**
 * Saves one browse filter for later re-runs.
 *
 * Also owns the lazy tenant identity: a guest who saves a search is searching,
 * so the Person row and Searcher role are created here instead of at signup.
 */
class SaveSearch extends Service
{
    use OnboardingHelpers;

    public function handle(User $user, string $name, array $filters): SavedSearch
    {
        return $this->transaction(function () use ($user, $name, $filters): SavedSearch {
            if ($user->person_id === null) {
                $this->createSearcherIdentity($user, [
                    'name' => $user->name,
                    'phone' => $user->phone,
                ]);

                $user->refresh();
            }

            return $user->savedSearches()->create([
                'name' => $name,
                'filters' => $filters,
            ]);
        });
    }
}
