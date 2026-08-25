<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();

            $table->string('tenant_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->json('settings')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');

            $table->index('plan_id');

            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->fullText(['name', 'slug', 'email'], 'organizations_fulltext_search');
            }
        });
    }

    public function down(): void
    {
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropFullText('organizations_fulltext_search');
            });
        }

        Schema::dropIfExists('organizations');
    }
};
