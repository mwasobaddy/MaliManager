<?php

namespace App\Support\Ai;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Compiles a model-supplied query specification into a real, read-only
 * database query — strictly from the semantic-layer allowlist. Anything
 * not declared in SemanticLayer is rejected, so the LLM can never read
 * arbitrary columns, tables, or write anything.
 */
final class DataQueryService
{
    public const int DEFAULT_LIMIT = 100;

    public const int MAX_LIMIT = 500;

    /**
     * @param  array{
     *     entity: string,
     *     measures?: list<array{type: string, column?: string}>,
     *     group_by?: list<string>,
     *     filters?: list<array{field: string, op: string, value: mixed}>,
     *     time_range?: array{field: string, from?: string, to?: string},
     *     limit?: int,
     * }  $spec
     * @return array{entity: string, measures: array, group_by: array, rows: array, row_count: int}
     */
    public function run(array $spec, Scope $scope): array
    {
        $entity = $spec['entity'];
        $def = SemanticLayer::entity($entity);
        $query = SemanticLayer::baseQuery($entity, $scope);

        $measures = $spec['measures'] ?? [['type' => 'count']];
        $groupBy = $spec['group_by'] ?? [];

        foreach ($groupBy as $column) {
            if (! in_array($column, $def['dimensions'], true)) {
                throw new \InvalidArgumentException("Cannot group by disallowed column: {$column}");
            }
        }

        $measureSelect = [];
        $firstMeasureAlias = 'count';

        foreach ($measures as $measure) {
            $type = $measure['type'] ?? null;

            if ($type === 'count') {
                $measureSelect[] = DB::raw('count(*) as count');
                $firstMeasureAlias = 'count';

                continue;
            }

            if (in_array($type, ['sum', 'avg'], true)) {
                $column = $measure['column'] ?? null;

                if ($column === null || ! in_array($column, $def['numeric'], true)) {
                    throw new \InvalidArgumentException("Cannot aggregate disallowed column: {$column}");
                }

                $alias = "{$type}_{$column}";
                $measureSelect[] = DB::raw("{$type}({$column}) as {$alias}");
                $firstMeasureAlias = $alias;

                continue;
            }

            throw new \InvalidArgumentException("Unsupported measure type: {$type}");
        }

        // SELECT = grouped dimensions first, then aggregates. Dimensions are
        // non-aggregated so they must appear in GROUP BY.
        $query->select(array_merge($groupBy, $measureSelect));

        if ($groupBy !== []) {
            $query->groupBy($groupBy);
        }

        foreach ($spec['filters'] ?? [] as $filter) {
            $this->applyFilter($query, $def['dimensions'], $filter);
        }

        if (isset($spec['time_range'])) {
            $this->applyTimeRange($query, $def['date_columns'], $spec['time_range']);
        }

        $limit = min((int) ($spec['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT);
        $query->limit($limit);

        if ($groupBy !== [] && in_array($groupBy[0], $def['date_columns'], true)) {
            $query->orderBy($groupBy[0], 'asc');
        } elseif ($groupBy !== []) {
            $query->orderByDesc($firstMeasureAlias);
        }

        $rows = $query->get()->map(fn ($model) => $model->getAttributes())->all();

        return [
            'entity' => $entity,
            'measures' => $measures,
            'group_by' => $groupBy,
            'rows' => $rows,
            'row_count' => count($rows),
        ];
    }

    /**
     * @param  array{dimension?: string, field?: string, op: string, value: mixed}  $filter
     */
    private function applyFilter(QueryBuilder $query, array $allowed, array $filter): void
    {
        $field = $filter['field'] ?? $filter['dimension'] ?? null;
        $op = $filter['op'] ?? '=';
        $value = $filter['value'] ?? null;

        if ($field === null || ! in_array($field, $allowed, true)) {
            throw new \InvalidArgumentException("Cannot filter disallowed column: {$field}");
        }

        $op = strtolower($op);

        if (! in_array($op, ['=', '!=', 'in', '>=', '<=', '>', '<'], true)) {
            throw new \InvalidArgumentException("Unsupported filter operator: {$op}");
        }

        if ($op === 'in') {
            $query->whereIn($field, (array) $value);

            return;
        }

        if ($op === '!=') {
            $query->whereNotIn($field, (array) $value);

            return;
        }

        $query->where($field, $op, $value);
    }

    /**
     * @param  array{field?: string, from?: string, to?: string}  $range
     */
    private function applyTimeRange(QueryBuilder $query, array $allowed, array $range): void
    {
        $field = $range['field'] ?? null;

        if ($field === null || ! in_array($field, $allowed, true)) {
            throw new \InvalidArgumentException("Cannot use disallowed date column: {$field}");
        }

        if (isset($range['from'])) {
            $query->where($field, '>=', $range['from']);
        }

        if (isset($range['to'])) {
            $query->where($field, '<=', $range['to']);
        }
    }
}
