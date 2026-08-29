<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Full-text indexes backing the assistant's `search_text` tool. These are
     * MySQL-only (InnoDB FULLTEXT); on SQLite the TextSearchService falls back
     * to a LIKE scan, which keeps the test suite portable. The column sets here
     * MUST match the `text_columns` declared in App\Support\Ai\SemanticLayer.
     *
     * @var array<string, list<string>>
     */
    private const FULLTEXT = [
        'organizations' => ['name', 'slug', 'email'],
        'properties' => ['name', 'address', 'slug'],
        'units' => ['name'],
        'leases' => ['agreement_text'],
        'expenses' => ['notes'],
        'maintenance_requests' => ['title', 'description', 'resolution_notes'],
        'land_parcels' => ['name', 'title_deed_number', 'notes'],
        'land_parcel_sections' => ['name', 'notes'],
        'inspections' => ['title', 'notes'],
    ];

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::FULLTEXT as $table => $columns) {
            $name = "ai_ft_{$table}";

            if (Schema::hasIndex($table, $name)) {
                continue;
            }

            $cols = implode(', ', array_map(fn ($c) => "`{$c}`", $columns));
            DB::statement("ALTER TABLE `{$table}` ADD FULLTEXT INDEX `{$name}` ({$cols})");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (array_keys(self::FULLTEXT) as $table) {
            $name = "ai_ft_{$table}";

            if (Schema::hasIndex($table, $name)) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$name}`");
            }
        }
    }
};
