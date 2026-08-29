<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\AiFeature;
use App\Exceptions\AssistantUnavailableException;
use App\Http\Controllers\Controller;
use App\Services\DraftingService;
use App\Support\Ai\AiGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Content drafting studio: occupant letters, notices and listing
 * descriptions generated from structured fields. Output is copy-only —
 * the platform never auto-sends AI-written communication.
 */
class DraftingController extends Controller
{
    public function __construct(
        private AiGateway $gateway,
        private DraftingService $drafting,
    ) {}

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

        try {
            $draft = $this->drafting->generate(
                $request->user(),
                $validated['type'],
                $validated['tone'] ?? null,
                $validated['fields'],
            );
        } catch (AssistantUnavailableException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json(['draft' => $draft]);
    }

    private function resolve(Request $request)
    {
        return $this->gateway->resolve($request->user(), AiFeature::ContentDrafting);
    }
}
