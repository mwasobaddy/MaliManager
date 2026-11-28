<?php

use App\Actions\AgreementTemplates\CreateDefaultAgreementTemplate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Organizations created before the shipped default existed get one —
     * but only when they never set up an org-wide template of their own,
     * so nobody's library gains a stranger at the top.
     */
    public function up(): void
    {
        Organization::query()->orderBy('id')->chunkById(100, function ($organizations): void {
            foreach ($organizations as $organization) {
                $ownerId = DB::table('organization_user')
                    ->where('organization_id', $organization->id)
                    ->where('is_owner', true)
                    ->orderBy('id')
                    ->value('user_id');

                $owner = $ownerId !== null ? User::find($ownerId) : null;

                if ($owner === null) {
                    continue;
                }

                app(CreateDefaultAgreementTemplate::class)->ensureForOrg($organization, $owner);
            }
        });
    }

    /**
     * Data backfill — nothing to reverse.
     */
    public function down(): void
    {
        //
    }
};
