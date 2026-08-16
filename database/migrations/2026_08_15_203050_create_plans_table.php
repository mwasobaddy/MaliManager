<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('currency', 3)->default('KES');
            $table->unsignedInteger('price')->default(0);
            $table->unsignedInteger('properties_limit')->nullable();
            $table->unsignedInteger('units_limit')->nullable();
            $table->boolean('has_dedicated_db')->default(false);
            $table->boolean('has_custom_domain')->default(false);
            $table->boolean('has_email_notifications')->default(false);
            $table->boolean('has_sms_notifications')->default(false);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
