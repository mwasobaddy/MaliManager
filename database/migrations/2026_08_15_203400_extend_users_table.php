<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('person_id')->nullable()->after('id')->constrained('persons')->nullOnDelete();
            $table->string('phone')->nullable()->after('email');
            $table->string('status')->default('active')->after('phone');
            $table->foreignId('created_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('person_id');
            $table->dropColumn('phone');
            $table->dropColumn('status');
            $table->dropConstrainedForeignId('created_by');
            $table->dropSoftDeletes();
        });
    }
};
