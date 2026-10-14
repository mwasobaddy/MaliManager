<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles became rows an admin can create, deactivate and delete, rather than
     * four fixed enum cases. `is_active` is what "deactivate" sets.
     *
     * There is deliberately no `is_system` column. Whether a role is protected
     * is derived from its name being a `PlatformRole` case — see
     * `App\Models\Role::isProtected()`. That keeps the protected set in code,
     * where the rest of the app reads it from, rather than in a column that
     * could drift out of step with the enum.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('guard_name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropColumn('is_active');
        });
    }
};
