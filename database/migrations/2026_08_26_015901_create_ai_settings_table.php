<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bring-your-own-key AI credentials. One row per owner: either an
     * Organization (shared key, gated by allow-list) or a User (personal
     * key). Keys are encrypted at rest and never returned to the client.
     */
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('owner');

            $table->string('provider');
            $table->string('model');
            $table->text('api_key');

            // Which AI features this credential may power.
            $table->json('features')->nullable();

            // Organization-scope access control: allow every member, or an
            // explicit list of user ids / platform + sub-role names.
            $table->boolean('allow_all_members')->default(false);
            $table->json('allowed_user_ids')->nullable();
            $table->json('allowed_roles')->nullable();

            // Optional soft cap on tokens per calendar month (0 = unlimited).
            $table->unsignedBigInteger('monthly_token_limit')->default(0);

            $table->timestamps();

            $table->unique(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
