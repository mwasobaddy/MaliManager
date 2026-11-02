<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_request_notes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('maintenance_request_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('body');

            // `internal` notes are staff-only working notes; `shared` notes are
            // visible to the occupant. Stored as the enum's backing value.
            $table->string('visibility')->default('internal');

            $table->timestamps();
            $table->softDeletes();

            // The list is always "notes for this request, newest first".
            $table->index(['maintenance_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_request_notes');
    }
};
