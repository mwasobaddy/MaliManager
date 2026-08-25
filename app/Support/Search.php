<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Central search across text columns. On MySQL/MariaDB this uses the
 * FULLTEXT indexes in boolean prefix mode; on other drivers (or for very
 * short terms, below InnoDB's minimum token size) it falls back to the
 * portable LIKE scan.
 */
class Search
{
    private const MIN_FULLTEXT_TERM_LENGTH = 3;

    /**
     * @param  array<int, string>  $columns
     */
    public static function apply(Builder $query, string $term, array $columns): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        if (self::supportsFullText() && mb_strlen($term) >= self::MIN_FULLTEXT_TERM_LENGTH) {
            $query->whereFullText($columns, self::booleanPrefixTerm($term), ['mode' => 'boolean']);

            return;
        }

        $query->where(function (Builder $query) use ($columns, $term): void {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', "%{$term}%");
            }
        });
    }

    private static function supportsFullText(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    /**
     * Strip boolean-mode operators and enable prefix matching per word so
     * "john doe" matches "johnda... johndoe@..." style rows.
     */
    private static function booleanPrefixTerm(string $term): string
    {
        $words = preg_split('/\s+/', preg_replace('/[+\-*>~()"]/', '', $term) ?? '', -1, PREG_SPLIT_NO_EMPTY);

        return collect($words)
            ->map(fn (string $word) => $word.'*')
            ->implode(' ');
    }
}
