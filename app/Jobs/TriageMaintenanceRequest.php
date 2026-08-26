<?php

namespace App\Jobs;

use App\Enums\AiFeature;
use App\Models\MaintenanceRequest;
use App\Models\Organization;
use App\Models\User;
use App\Support\Ai\AiGateway;
use App\Support\StorageLayout;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Messages\UserMessage;

/**
 * Suggests a priority for a freshly created maintenance request using the
 * credential owner's LLM. Purely advisory: staff decisions stay in the
 * `priority` column; the suggestion lands in `ai_priority`.
 */
class TriageMaintenanceRequest implements ShouldQueue
{
    use Queueable;

    public function __construct(public MaintenanceRequest $maintenanceRequest)
    {
        $this->afterCommit = true;
    }

    public function handle(AiGateway $gateway): void
    {
        $raiser = User::find($this->maintenanceRequest->raised_by);

        if (! $raiser) {
            return;
        }

        $organization = $this->maintenanceRequest->organization_id
            ? Organization::find($this->maintenanceRequest->organization_id)
            : null;

        $credential = $gateway->resolve($raiser, AiFeature::MaintenanceTriage, $organization);

        if ($credential === null) {
            return;
        }

        $startedAt = microtime(true);

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

            $prompt = "Title: {$this->maintenanceRequest->title}"
                ."\nDescription: {$this->maintenanceRequest->description}";

            // Vision triage: include the first attached photo when present.
            $photo = $this->maintenanceRequest->getFirstMedia('photos');
            $images = [];

            if ($photo && str_starts_with((string) $photo->mime_type, 'image/')) {
                $images[] = Image::fromStoragePath($photo->getPathRelativeToRoot(), StorageLayout::disk());
            }

            $response = Prism::structured()
                ->using($credential->prismProvider(), $credential->model)
                ->usingProviderConfig($credential->requestConfig())
                ->withSchema($schema)
                ->withSystemPrompt(
                    'You triage property maintenance requests for a property '
                    .'management company. Water leaks, power failures, security issues '
                    .'and anything causing ongoing damage are urgent. Cosmetic issues '
                    .'are low. If a photo is provided, use what you see to refine the '
                    .'triage. Respond with priority (low|medium|high|urgent) and one reason.',
                );

            if ($images !== []) {
                $response = $response->withMessages([
                    new UserMessage($prompt, $images),
                ])->asStructured();
            } else {
                $response = $response->withPrompt($prompt)->asStructured();
            }

            $structured = $response->structured;
            $suggestion = is_array($structured)
                ? ($structured['priority'] ?? null)
                : ($structured->priority ?? null);

            if (is_string($suggestion) && in_array($suggestion, ['low', 'medium', 'high', 'urgent'], true)) {
                $this->maintenanceRequest->update(['ai_priority' => $suggestion]);
            }

            $gateway->log(
                $credential,
                $raiser,
                AiFeature::MaintenanceTriage,
                promptTokens: $response->usage->promptTokens,
                completionTokens: $response->usage->completionTokens,
                durationMs: (int) ((microtime(true) - $startedAt) * 1000),
            );
        } catch (\Throwable $e) {
            $gateway->log(
                $credential,
                $raiser,
                AiFeature::MaintenanceTriage,
                status: 'error',
                error: $e->getMessage(),
            );
        }
    }
}
