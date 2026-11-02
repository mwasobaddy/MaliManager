<?php

namespace App\Actions\Tenants\Concerns;

use App\Models\Organization;
use Illuminate\Support\Str;

trait OrganizationHelpers
{
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (Organization::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.($suffix++);
        }

        return $slug;
    }
}
