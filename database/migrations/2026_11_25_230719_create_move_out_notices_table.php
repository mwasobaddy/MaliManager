<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A tenant's 30-day move-out notice against their lease. Pending until
     * the landlord acknowledges it or the tenant withdraws it; an ended
     * lease makes its notices moot, which the readers filter out rather
     * than the writer deleting history.
     */
    public function up(): void
    {
        Schema::create('move_out_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('move_out_date');
            $table->string('status', 16)->default('pending')->index();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('move_out_notices');
    }
};
