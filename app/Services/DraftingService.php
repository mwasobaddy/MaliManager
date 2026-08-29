<?php

namespace App\Services;

use App\Enums\AiFeature;
use App\Models\User;
use App\Support\Ai\AiGateway;
use Prism\Prism\Facades\Prism;

/**
 * Copy-only content drafting (occupant letters, notices, listings). Shared
 * by the drafting studio and the assistant's `draft_communication` tool so
 * the prompt + credential resolution live in one place.
 */
final class DraftingService
{
    private const array PROMPTS = [
        'rent_reminder' => 'Write a rent payment reminder to a tenant.',
        'lease_expiry_notice' => 'Write a lease expiry / renewal notice to a tenant.',
        'move_out_letter' => 'Write a move-out instruction letter to a tenant.',
        'listing_description' => 'Write an attractive rental listing description for prospective tenants.',
    ];

    public function __construct(private AiGateway $gateway) {}

    /**
     * @throws AssistantUnavailableException when no credential is configured.
     */
    public function generate(User $user, string $type, ?string $tone, array $fields): string
    {
        $credential = $this->gateway->resolve($user, AiFeature::ContentDrafting);

        if ($credential === null) {
            throw new AssistantUnavailableException('AI drafting is not configured for your account.');
        }

        $startedAt = microtime(true);

        try {
            $response = Prism::text()
                ->using($credential->prismProvider(), $credential->model)
                ->usingProviderConfig($credential->requestConfig())
                ->withSystemPrompt(
                    'You write property management correspondence for '
                    .$user->name.', a Kenyan property manager. '
                    .'Tone: '.($tone ?? 'friendly').'. Keep it under 200 words, '
                    .'ready to copy into an email or SMS. No placeholders left unfilled — '
                    .'use the provided details.',
                )
                ->withPrompt(
                    (self::PROMPTS[$type] ?? 'Write a property management message.')
                    ."\n\nDetails:\n"
                    .collect($fields)->map(fn ($value, $key) => ucfirst(str_replace('_', ' ', $key)).': '.$value)->implode("\n"),
                )
                ->asText();

            $this->gateway->log(
                $credential,
                $user,
                AiFeature::ContentDrafting,
                promptTokens: $response->usage->promptTokens,
                completionTokens: $response->usage->completionTokens,
                durationMs: (int) ((microtime(true) - $startedAt) * 1000),
            );

            return $response->text;
        } catch (\Throwable $e) {
            $this->gateway->log($credential, $user, AiFeature::ContentDrafting, status: 'error', error: $e->getMessage());
            throw new AssistantUnavailableException('The draft could not be generated right now. Please try again.', 0, $e);
        }
    }

    /**
     * @return array{type: string, tone: string|null, fields: array}
     */
    public static function exampleSpec(): array
    {
        return [
            'type' => 'rent_reminder',
            'tone' => 'friendly',
            'fields' => ['tenant_name' => 'Amina', 'amount' => '45000', 'due_date' => '2026-09-01'],
        ];
    }
}
