<?php

namespace App\Support;

/**
 * Free-text search terms shared by the index scopes.
 *
 * The query splits on whitespace: every term must match somewhere (AND
 * across terms) while each term may match any of the searched fields (OR
 * within a term). That way a pasted full name ("Jane Doe") narrows instead
 * of matching nothing because no single column holds the whole string.
 */
final class SearchTerms
{
    /**
     * @return list<string>
     */
    public static function split(?string $search): array
    {
        $terms = preg_split('/\s+/', trim((string) $search), -1, PREG_SPLIT_NO_EMPTY);

        return $terms === false ? [] : array_values($terms);
    }
}
