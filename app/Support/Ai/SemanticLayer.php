<?php

namespace App\Support\Ai;

use App\Models\Expense;
use App\Models\Inspection;
use App\Models\LandParcel;
use App\Models\LandParcelSection;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;

/**
 * The semantic layer is the single gatekeeper for what the assistant can
 * read. Every column, measure and dimension the LLM may reference is
 * declared here; the compiler rejects anything not on this list. This is
 * what keeps the agent read-only and leak-proof — the model can only ask
 * for data we have explicitly exposed, and scope is injected server-side.
 */
final class SemanticLayer
{
    /**
     * @return array<string, array{
     *     label: string,
     *     model: class-string<Builder>,
     *     scopes: list<string>,
     *     numeric: list<string>,
     *     dimensions: list<string>,
     *     date_columns: list<string>,
     *     text_columns: list<string>,
     *     scope: callable(Builder, Scope): Builder,
     * }>
     */
    public static function entities(): array
    {
        return [
            'organizations' => [
                'label' => 'Organizations (tenants on the platform)',
                'model' => Organization::class,
                'scopes' => [Scope::PLATFORM],
                'numeric' => [],
                'dimensions' => ['status', 'plan_id', 'created_at'],
                'date_columns' => ['created_at'],
                'text_columns' => ['name', 'slug', 'email', 'phone', 'status'],
                'scope' => fn (Builder $q) => $q,
            ],
            'properties' => [
                'label' => 'Properties',
                'model' => Property::class,
                'scopes' => [Scope::ORG, Scope::PLATFORM],
                'numeric' => [],
                'dimensions' => ['organization_id', 'status', 'city', 'created_at'],
                'date_columns' => ['created_at'],
                'text_columns' => ['name', 'slug', 'address', 'city', 'status'],
                'scope' => fn (Builder $q, Scope $s) => $s->type === Scope::ORG
                    ? $q->where('organization_id', $s->organizationId)
                    : $q,
            ],
            'units' => [
                'label' => 'Units',
                'model' => Unit::class,
                'scopes' => [Scope::ORG, Scope::PLATFORM],
                'numeric' => ['monthly_rent', 'deposit'],
                'dimensions' => ['property_id', 'status', 'type', 'created_at'],
                'date_columns' => ['created_at'],
                'text_columns' => ['name', 'type', 'status'],
                'scope' => fn (Builder $q, Scope $s) => $s->type === Scope::ORG
                    ? $q->whereHas('property', fn ($p) => $p->where('organization_id', $s->organizationId))
                    : $q,
            ],
            'leases' => [
                'label' => 'Leases',
                'model' => Lease::class,
                'scopes' => [Scope::ORG, Scope::PERSON, Scope::PLATFORM],
                'numeric' => ['rent_amount', 'deposit'],
                'dimensions' => [
                    'organization_id', 'property_id', 'unit_id', 'person_id',
                    'status', 'rent_frequency', 'currency', 'starts_at', 'ends_at', 'created_at',
                    'land_parcel_id', 'land_parcel_section_id',
                ],
                'date_columns' => ['starts_at', 'ends_at', 'created_at'],
                'text_columns' => ['status', 'agreement_text', 'rent_frequency'],
                'scope' => fn (Builder $q, Scope $s) => match ($s->type) {
                    Scope::ORG => $q->where('organization_id', $s->organizationId),
                    Scope::PERSON => $q->where('person_id', $s->personId),
                    default => $q,
                },
            ],
            'expenses' => [
                'label' => 'Expenses',
                'model' => Expense::class,
                'scopes' => [Scope::ORG, Scope::PLATFORM],
                'numeric' => ['amount'],
                'dimensions' => ['organization_id', 'unit_id', 'category', 'currency', 'spent_on'],
                'date_columns' => ['spent_on'],
                'text_columns' => ['category', 'notes', 'currency'],
                'scope' => fn (Builder $q, Scope $s) => $s->type === Scope::ORG
                    ? $q->where('organization_id', $s->organizationId)
                    : $q,
            ],
            'maintenance_requests' => [
                'label' => 'Maintenance requests',
                'model' => MaintenanceRequest::class,
                'scopes' => [Scope::ORG, Scope::PERSON, Scope::PLATFORM],
                'numeric' => [],
                'dimensions' => ['organization_id', 'property_id', 'unit_id', 'status', 'priority', 'created_at', 'resolved_at'],
                'date_columns' => ['created_at', 'resolved_at'],
                'text_columns' => ['title', 'description', 'status', 'priority', 'ai_priority'],
                'scope' => fn (Builder $q, Scope $s) => match ($s->type) {
                    Scope::ORG => $q->where('organization_id', $s->organizationId),
                    Scope::PERSON => $q->where('raised_by', $s->userId),
                    default => $q,
                },
            ],
            'land_parcels' => [
                'label' => 'Land parcels',
                'model' => LandParcel::class,
                'scopes' => [Scope::ORG, Scope::PLATFORM],
                'numeric' => ['acreage'],
                'dimensions' => ['organization_id', 'status', 'zoning', 'city', 'created_at'],
                'date_columns' => ['created_at'],
                'text_columns' => ['name', 'slug', 'title_deed_number', 'address', 'city', 'zoning', 'status', 'notes'],
                'scope' => fn (Builder $q, Scope $s) => $s->type === Scope::ORG
                    ? $q->where('organization_id', $s->organizationId)
                    : $q,
            ],
            'land_parcel_sections' => [
                'label' => 'Land parcel sections',
                'model' => LandParcelSection::class,
                'scopes' => [Scope::ORG, Scope::PLATFORM],
                'numeric' => ['area'],
                'dimensions' => ['land_parcel_id', 'status', 'created_at'],
                'date_columns' => ['created_at'],
                'text_columns' => ['name', 'status', 'notes'],
                'scope' => fn (Builder $q, Scope $s) => $s->type === Scope::ORG
                    ? $q->whereHas('landParcel', fn ($p) => $p->where('organization_id', $s->organizationId))
                    : $q,
            ],
            'inspections' => [
                'label' => 'Inspections',
                'model' => Inspection::class,
                'scopes' => [Scope::ORG, Scope::PLATFORM],
                'numeric' => [],
                'dimensions' => ['organization_id', 'property_id', 'unit_id', 'created_at', 'inspection_date'],
                'date_columns' => ['created_at', 'inspection_date'],
                'text_columns' => ['title', 'notes'],
                'scope' => fn (Builder $q, Scope $s) => $s->type === Scope::ORG
                    ? $q->where('organization_id', $s->organizationId)
                    : $q,
            ],
        ];
    }

    /**
     * @throws \InvalidArgumentException when the entity is not exposed.
     */
    public static function entity(string $name): array
    {
        $entities = self::entities();

        if (! isset($entities[$name])) {
            throw new \InvalidArgumentException("Unknown entity: {$name}");
        }

        return $entities[$name];
    }

    /**
     * A read-only query builder for the entity, already scoped to the
     * caller. Never returns a builder that can write.
     */
    public static function baseQuery(string $entity, Scope $scope): Builder
    {
        $def = self::entity($entity);

        if (! in_array($scope->type, $def['scopes'], true)) {
            throw new \InvalidArgumentException("Entity {$entity} is not available in the {$scope->type} scope.");
        }

        /** @var Builder $query */
        $query = ($def['model'])::query();

        return ($def['scope'])($query, $scope);
    }

    /**
     * The catalog the model sees via `describe_data` — only what the
     * current scope is allowed to query.
     */
    public static function catalogFor(string $scopeType): array
    {
        $out = [];

        foreach (self::entities() as $name => $def) {
            if (! in_array($scopeType, $def['scopes'], true)) {
                continue;
            }

            $measures = [['type' => 'count']];

            foreach ($def['numeric'] as $column) {
                $measures[] = ['type' => 'sum', 'column' => $column];
                $measures[] = ['type' => 'avg', 'column' => $column];
            }

            $out[$name] = [
                'label' => $def['label'],
                'measures' => $measures,
                'dimensions' => $def['dimensions'],
                'date_columns' => array_values($def['date_columns']),
                'text_searchable' => $def['text_columns'] !== [],
            ];
        }

        return $out;
    }
}
