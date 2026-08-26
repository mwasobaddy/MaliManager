<?php

namespace App\Services;

use App\Enums\AiFeature;
use App\Models\Lease;
use App\Models\Property;
use App\Models\User;
use App\Support\Ai\AiGateway;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

/**
 * AI-suggested renewal rent for an expiring lease, derived from current
 * pricing and tenure. Advisory only — the owner decides what to offer.
 *
 * Throws RuntimeException when the caller has no usable AI credential
 * (controller translates to 403); provider errors propagate as-is.
 */
class AiRenewalService extends Service
{
    public function __construct(private AiGateway $gateway) {}

    /**
     * @return array{suggested_rent: float, reasoning: string|null}
     */
    public function suggest(User $user, Property $property, Lease $lease): array
    {
        $credential = $this->gateway->resolve(
            $user,
            AiFeature::AskData,
            $property->organization,
        );

        if ($credential === null) {
            throw new \RuntimeException('AI is not configured for your account.');
        }

        $tenureMonths = $lease->starts_at?->diffInMonths(now()) ?? 0;

        $startedAt = microtime(true);

        try {
            $schema = new ObjectSchema(
                name: 'renewal_suggestion',
                description: 'Suggested renewal terms for a lease',
                properties: [
                    new NumberSchema('suggested_rent', 'Suggested monthly rent for the renewal'),
                    new StringSchema('reasoning', 'One or two sentences explaining the suggestion'),
                ],
                requiredFields: ['suggested_rent', 'reasoning'],
            );

            $response = Prism::structured()
                ->using($credential->prismProvider(), $credential->model)
                ->usingProviderConfig($credential->requestConfig())
                ->withSchema($schema)
                ->withSystemPrompt(
                    'You suggest renewal rents for a Kenyan property manager. Base the '
                    .'suggestion on the current rent and tenure: long-tenured reliable '
                    .'tenants get modest increases (0-7%); very short tenures may '
                    .'justify more. Stay in the same currency.',
                )
                ->withPrompt(sprintf(
                    'Current monthly rent: %s %s. Tenure so far: %d months. Lease started %s, ends %s.',
                    $lease->rent_amount ?? 'unknown',
                    $lease->currency ?? '',
                    $tenureMonths,
                    $lease->starts_at?->toDateString() ?? 'unknown',
                    $lease->ends_at?->toDateString() ?? 'open',
                ))
                ->asStructured();

            $this->gateway->log(
                $credential,
                $user,
                AiFeature::AskData,
                promptTokens: $response->usage->promptTokens,
                completionTokens: $response->usage->completionTokens,
                durationMs: (int) ((microtime(true) - $startedAt) * 1000),
            );

            return [
                'suggested_rent' => round((float) ($response->structured['suggested_rent'] ?? 0), 2),
                'reasoning' => $response->structured['reasoning'] ?? null,
            ];
        } catch (\Throwable $e) {
            $this->gateway->log(
                $credential,
                $user,
                AiFeature::AskData,
                status: 'error',
                error: $e->getMessage(),
            );

            throw $e;
        }
    }
}
