<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persisted AI assistant conversations so the assistant remembers context
     * across sessions. A conversation belongs to a user within a scope
     * (platform / org / person); messages carry the rendered artifacts too.
     */
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('scope', 16); // platform | org | person
            $table->string('title')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'scope']);
            $table->index(['organization_id', 'scope']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ai_conversation_id');
            $table->string('role', 16); // user | assistant | error
            $table->text('content');
            $table->json('artifacts')->nullable();
            $table->timestamps();

            $table->foreign('ai_conversation_id')->references('id')->on('ai_conversations')->cascadeOnDelete();
            $table->index(['ai_conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
