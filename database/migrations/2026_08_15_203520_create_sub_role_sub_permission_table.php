<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_role_sub_permission', function (Blueprint $table) {
            $table->foreignId('sub_role_id')->constrained('sub_roles')->cascadeOnDelete();
            $table->foreignId('sub_permission_id')->constrained('sub_permissions')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['sub_role_id', 'sub_permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_role_sub_permission');
    }
};
