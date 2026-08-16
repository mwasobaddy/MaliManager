<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_user_id')->constrained('organization_user')->cascadeOnDelete();
            $table->morphs('delegatable');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_user_id', 'delegatable_type', 'delegatable_id'], 'delegations_org_user_delegatable_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delegations');
    }
};
