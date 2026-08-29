<?php

namespace App\Services;

use App\Enums\AiFeature;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Support\Ai\AiGateway;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

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
        $credential = $this->gateway->resolve($raiser, AiFeature::MaintenanceTriage, $request->organization_id ? null : null);

        if ($credential === null) {
            throw new AssistantUnavailableException('AI triage is not configured for your account.');
        }

        $startedAt = microtime(true);
        $prompt = 'Title: '.$request->title."\nDescription: ".$request->description;

        try {
            $schema = new ObjectSchema(
                name: 'triage_result',
                description: 'Triage of a maintenance request',
                properties: [
                    new StringSchema('priority', 'Suggested priority: low, medium, high or urgent'),
                    new StringSchema('reason', 'One-sentence justification'),
                ],
                requiredFields: ['priority', 'reason'],
            );

            $response = Prism::structured()
                ->using($credential->prismProvider(), $credential->model)
                ->usingProviderConfig($credential->requestConfig())
                ->withSchema($schema)
                ->withSystemPrompt(
                    'You triage property maintenance requests for a property '
                    .'management company. Water leaks, power failures, security issues '
                    .'and anything causing ongoing damage are urgent. Cosmetic issues '
                    .'are low. Respond with priority (low|medium|high|urgent) and one reason.',
                )
                ->withPrompt($prompt)
                ->asStructured();

            $structured = $response->structured;
            $priority = is_array($structured) ? ($structured['priority'] ?? null) : ($structured->priority ?? null);
            $reason = is_array($structured) ? ($structured['reason'] ?? null) : ($structured->reason ?? null);

            if (is_string($priority) && in_array($priority, self::PRIORITIES, true)) {
                $request->update(['ai_priority' => $priority]);
            }

            $this->gateway->log(
                $credential,
                $raiser,
                AiFeature::MaintenanceTriage,
                promptTokens: $response->usage->promptTokens,
                completionTokens: $response->usage->completionTokens,
                durationMs: (int) ((microtime(true) - $startedAt) * 1000),
            );

            return [
                'priority' => is_string($priority) ? $priority : 'unknown',
                'reason' => is_string($reason) ? $reason : '',
            ];
        } catch (\Throwable $e) {
            $this->gateway->log($credential, $raiser, AiFeature::MaintenanceTriage, status: 'error', error: $e->getMessage());
            throw new AssistantUnavailableException('Triage could not be completed right now.', 0, $e);
        }
    }
}
