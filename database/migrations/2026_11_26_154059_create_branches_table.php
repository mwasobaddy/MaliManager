<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Branches group an organization's properties and land parcels so a
     * multi-branch operator can scope staff, and later reports, by branch.
     * Every organization gets a Main branch; existing assets are assigned
     * to it so no query ever has to treat "no branch" as a third state.
     */
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            $table->index(['organization_id', 'branch_id']);
        });

        Schema::table('land_parcels', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            $table->index(['organization_id', 'branch_id']);
        });

        DB::table('organizations')->orderBy('id')->each(function (object $organization) {
            $branchId = DB::table('branches')->insertGetId([
                'organization_id' => $organization->id,
                'name' => 'Main',
                'slug' => 'main',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('properties')->where('organization_id', $organization->id)->update(['branch_id' => $branchId]);
            DB::table('land_parcels')->where('organization_id', $organization->id)->update(['branch_id' => $branchId]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('land_parcels', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['organization_id', 'branch_id']);
            $table->dropColumn('branch_id');
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['organization_id', 'branch_id']);
            $table->dropColumn('branch_id');
        });

        Schema::dropIfExists('branches');
    }
};
