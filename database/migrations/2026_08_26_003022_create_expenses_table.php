<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Target asset: a property or a land parcel.
            $table->nullableMorphs('expenseable');

            // Optional unit when the expense targets a specific unit inside
            // an expenseable property.
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();

            $table->string('category');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('KES');
            $table->date('spent_on');
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'spent_on']);
            $table->index(['organization_id', 'category']);

            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->fullText(['category', 'notes', 'currency'], 'expenses_fulltext_search');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
