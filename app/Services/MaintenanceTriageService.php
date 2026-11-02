<?php

namespace App\Services;

use App\Actions\Maintenance\SuggestMaintenanceTriage;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Support\Ai\AiGateway;

/**
 * Advisory maintenance triage. Shared by the queued job (on create) and the
 * assistant's `triage_maintenance` tool. Purely advisory: the suggestion
 * lands in `ai_priority`; staff decisions stay authoritative.
 */
final class MaintenanceTriageService
{
    private const array PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public function __construct(private AiGateway $gateway) {}

    /**
     * @return array{priority: string, reason: string}
     */
    public function suggest(MaintenanceRequest $request, User $raiser): array
    {
        return app(SuggestMaintenanceTriage::class)->handle($request, $raiser);
    }
}
