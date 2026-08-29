<?php

namespace App\Support\Ai;

/**
 * Unstructured search over free-text columns declared in the semantic layer.
 * On MySQL it uses the InnoDB FULLTEXT index (MATCH ... AGAINST) for speed; on
 * SQLite (and any other engine) it falls back to a parameterised LIKE scan.
 * Either way it uses the same server-side scope injection, so it can never
 * reach rows the caller is not allowed to see. Also rejects any entity/column
 * outside the layer.
 */
final class TextSearchService
{
    public const int MAX_LIMIT = 50;

    /**
     * @return array{entity: string, term: string, rows: array, row_count: int}
     */
    public function search(string $entity, string $term, Scope $scope, int $limit = 20): array
    {
        $def = SemanticLayer::entity($entity);

        if ($def['text_columns'] === []) {
            throw new \InvalidArgumentException("Entity {$entity} has no searchable text columns.");
        }

        $query = SemanticLayer::baseQuery($entity, $scope);

        $term = trim($term);

        if ($term === '') {
            throw new \InvalidArgumentException('Search term must not be empty.');
        }

        if ($query->getConnection()->getDriverName() === 'mysql') {
            $this->applyFulltext($query, $def['text_columns'], $term);
        } else {
            $like = '%'.$term.'%';

            $query->where(function ($q) use ($def, $like): void {
                foreach ($def['text_columns'] as $column) {
                    $q->orWhere($column, 'like', $like);
                }
            });
        }

        $select = array_merge(['id'], $def['text_columns']);

        $rows = $query->limit(min($limit, self::MAX_LIMIT))
            ->get($select)
            ->map(fn ($row) => (array) $row)
            ->all();

        return [
            'entity' => $entity,
            'term' => $term,
            'rows' => $rows,
            'row_count' => count($rows),
        ];
    }

    /**
     * Boolean-mode FULLTEXT match. Each whitespace-separated word is turned
     * into a prefix token (word*) so partial words still match.
     *
     * @param  list<string>  $columns
     */
    private function applyFulltext($query, array $columns, string $term): void
    {
        $cols = implode(', ', array_map(fn ($c) => "`{$c}`", $columns));
        $boolean = implode(' ', array_map(
            fn ($word) => preg_replace('/[^a-zA-Z0-9]/', '', $word).'*',
            preg_split('/\s+/', $term) ?: [],
        ));

        if ($boolean === '') {
            return;
        }

        $query->whereRaw("MATCH ({$cols}) AGAINST (? IN BOOLEAN MODE)", [$boolean]);
    }
}
