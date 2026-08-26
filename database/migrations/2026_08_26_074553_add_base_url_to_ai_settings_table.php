<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional endpoint override for providers that are not native to
     * Prism (e.g. NVIDIA NIM routed through the OpenAI driver), or for
     * self-hosted/proxied endpoints. NULL = provider default.
     */
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->string('base_url')->nullable()->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn('base_url');
        });
    }
};
