<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\AiFeature;
use App\Http\Controllers\Controller;
use App\Support\Ai\AiGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Prism\Prism\Facades\Prism;

/**
 * Content drafting studio: occupant letters, notices and listing
 * descriptions generated from structured fields. Output is copy-only —
 * the platform never auto-sends AI-written communication.
 */
class DraftingController extends Controller
{
    public function __construct(private AiGateway $gateway) {}

    public function page(Request $request): Response
    {
        return Inertia::render('tenant/drafting', [
            'enabled' => $this->resolve($request) !== null,
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['rent_reminder', 'lease_expiry_notice', 'move_out_letter', 'listing_description'])],
            'tone' => ['nullable', Rule::in(['formal', 'friendly'])],
            'fields' => ['required', 'array'],
            'fields.*' => ['nullable', 'string', 'max:500'],
        ]);

        $credential = $this->resolve($request);

        if ($credential === null) {
            return response()->json(['error' => 'AI is not configured for your account.'], 403);
        }

        $startedAt = microtime(true);

        $prompts = [
            'rent_reminder' => 'Write a rent payment reminder to a tenant.',
            'lease_expiry_notice' => 'Write a lease expiry / renewal notice to a tenant.',
            'move_out_letter' => 'Write a move-out instruction letter to a tenant.',
            'listing_description' => 'Write an attractive rental listing description for prospective tenants.',
        ];

        try {
            $response = Prism::text()
                ->using($credential->prismProvider(), $credential->model)
                ->usingProviderConfig($credential->requestConfig())
                ->withSystemPrompt(
                    'You write property management correspondence for '
                    .$request->user()->name.', a Kenyan property manager. '
                    .'Tone: '.($validated['tone'] ?? 'friendly').'. Keep it under 200 words, '
                    .'ready to copy into an email or SMS. No placeholders left unfilled — '
                    .'use the provided details.',
                )
                ->withPrompt(
                    $prompts[$validated['type']]."\n\nDetails:\n"
                    .collect($validated['fields'])->map(fn ($value, $key) => ucfirst(str_replace('_', ' ', $key)).': '.$value)->implode("\n"),
                )
                ->asText();

            $this->gateway->log(
                $credential,
                $request->user(),
                AiFeature::ContentDrafting,
                promptTokens: $response->usage->promptTokens,
                completionTokens: $response->usage->completionTokens,
                durationMs: (int) ((microtime(true) - $startedAt) * 1000),
            );

            return response()->json(['draft' => $response->text]);
        } catch (\Throwable $e) {
            $this->gateway->log(
                $credential,
                $request->user(),
                AiFeature::ContentDrafting,
                status: 'error',
                error: $e->getMessage(),
            );

            return response()->json([
                'error' => 'The draft could not be generated right now. Please try again.',
            ], 502);
        }
    }

    private function resolve(Request $request)
    {
        return $this->gateway->resolve($request->user(), AiFeature::ContentDrafting);
    }
}
