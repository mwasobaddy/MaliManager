<?php

namespace App\Http\Controllers;

use App\Enums\AiFeature;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Support\Ai\AiGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Facades\Tool;

/**
 * Occupant-facing AI assistant. Strictly scoped to the signed-in user's
 * OWN data: tools filter every query by person_id server-side.
 */
class SearcherAssistantController extends Controller
{
    public function __construct(private AiGateway $gateway) {}

    public function page(Request $request): Response
    {
        return Inertia::render('searcher/assistant', [
            'enabled' => $this->resolve($request) !== null,
        ]);
    }

    public function ask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $credential = $this->resolve($request);

        if ($credential === null || ! $user->person_id) {
            return response()->json(['error' => 'AI is not configured for your account.'], 403);
        }

        $personId = $user->person_id;

        $tools = [
            Tool::as('get_my_leases')
                ->for('Get your rental leases: property, unit, rent, dates and status')
                ->using(fn (): string => (string) json_encode(
                    Lease::query()
                        ->where('person_id', $personId)
                        ->with(['property:id,name', 'unit:id,name'])
                        ->orderByDesc('starts_at')
                        ->get(['property_id', 'unit_id', 'rent_amount', 'currency', 'starts_at', 'ends_at', 'status'])
                        ->map(fn (Lease $lease) => [
                            'property' => $lease->property?->name,
                            'unit' => $lease->unit?->name,
                            'monthly_rent' => $lease->rent_amount,
                            'currency' => $lease->currency,
                            'starts_at' => $lease->starts_at?->toDateString(),
                            'ends_at' => $lease->ends_at?->toDateString() ?? ($lease->status === 'active' ? 'Open-ended' : null),
                            'status' => $lease->status,
                        ])
                        ->all(),
                )),
            Tool::as('get_my_maintenance_requests')
                ->for('Get your maintenance requests with their current status')
                ->using(fn (): string => (string) json_encode(
                    MaintenanceRequest::query()
                        ->where('raised_by', $user->id)
                        ->orderByDesc('created_at')
                        ->get(['title', 'status', 'priority', 'created_at', 'resolved_at'])
                        ->map(fn (MaintenanceRequest $item) => [
                            'title' => $item->title,
                            'status' => $item->status,
                            'priority' => $item->priority,
                            'raised_on' => $item->created_at?->toDateString(),
                            'resolved_on' => $item->resolved_at?->toDateString(),
                        ])
                        ->all(),
                )),
        ];

        $startedAt = microtime(true);

        try {
            $response = Prism::text()
                ->using($credential->prismProvider(), $credential->model)
                ->usingProviderConfig($credential->requestConfig())
                ->withSystemPrompt(
                    'You are the MaliManager tenant assistant. Answer questions about the '
                    .'signed-in tenant\'s OWN rentals and maintenance requests using the '
                    .'tools only — never invent details. Today is '.now()->toDateString().'. Be concise.',
                )
                ->withMaxSteps(4)
                ->withTools($tools)
                ->withPrompt($validated['question'])
                ->asText();

            $this->gateway->log(
                $credential,
                $user,
                AiFeature::AskData,
                promptTokens: $response->usage->promptTokens,
                completionTokens: $response->usage->completionTokens,
                durationMs: (int) ((microtime(true) - $startedAt) * 1000),
            );

            return response()->json(['answer' => $response->text]);
        } catch (\Throwable $e) {
            $this->gateway->log(
                $credential,
                $user,
                AiFeature::AskData,
                status: 'error',
                error: $e->getMessage(),
            );

            return response()->json([
                'error' => 'The assistant could not answer right now. Please try again.',
            ], 502);
        }
    }

    private function resolve(Request $request)
    {
        return $this->gateway->resolve($request->user(), AiFeature::AskData);
    }
}
