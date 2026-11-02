<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_meter_readings', function (Blueprint $table) {
            $table->id();

            // Denormalized from the unit's property: every list and every
            // authorization check is organization-scoped, and joining three
            // tables to find that out is a query we would write on every row.
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();

            $table->string('utility');

            // Three decimals: a meter reads to a tenth of a kWh or m³, and
            // rounding at the column would lose the difference between two
            // consecutive readings on a low-consumption unit.
            $table->decimal('reading', 14, 3);

            // Consumption for this period, computed from the previous reading
            // when one exists. Null on the first reading, because "used since
            // when?" has no answer yet — that is different from zero use.
            $table->decimal('consumption', 14, 3)->nullable();

            $table->date('read_on');
            $table->text('notes')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One reading per meter per day. Also the index the previous-reading
            // lookup uses, since it leads with unit_id and utility.
            $table->unique(['unit_id', 'utility', 'read_on']);

            $table->index(['organization_id', 'read_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_meter_readings');
    }
};
