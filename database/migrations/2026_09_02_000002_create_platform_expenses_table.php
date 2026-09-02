<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_expenses', function (Blueprint $table) {
            $table->id();

            $table->string('category');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('KES');

            // When the cost was incurred; used for the expense graphs.
            $table->date('spent_on');

            $table->string('vendor_name')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('spent_on');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_expenses');
    }
};
