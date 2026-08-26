<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\AiFeature;
use App\Http\Controllers\Controller;
use App\Services\Reporting\OrgInsightsService;
use App\Support\Ai\AiGateway;
use App\Support\TenancyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Facades\Tool;

/**
 * Ask-your-data assistant. The LLM never sees the database — it calls
 * read-only insight tools whose answers come from our own queries, so
 * numbers can't be hallucinated.
 */
class AssistantController extends Controller
{
    public function __construct(private AiGateway $gateway) {}

    public function page(Request $request): Response
    {
        return Inertia::render('tenant/assistant', [
            'enabled' => $this->resolve($request) !== null,
        ]);
    }

    public function ask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
        ]);

        $credential = $this->resolve($request);

        if ($credential === null) {
            return response()->json(['error' => 'AI is not configured for your account.'], 403);
        }

        $organization = TenancyContext::organization();
        $insights = new OrgInsightsService($organization?->id);

        $tools = [
            Tool::as('get_vacancy_summary')
                ->for('Get vacant vs occupied unit counts per property')
                ->using(fn (): string => (string) json_encode($insights->vacancySummary())),
            Tool::as('get_lease_summary')
                ->for('Get active lease count and leases expiring within 60 days')
                ->using(fn (): string => (string) json_encode($insights->leaseSummary())),
            Tool::as('get_expense_totals')
                ->for('Get expense totals grouped by category. Pass a category to filter.')
                ->withStringParameter('category', 'Optional category filter: maintenance, renovation, cleaning, utilities, security, other', '')
                ->using(fn (string $category): string => (string) json_encode(
                    $insights->expenseTotals($category !== '' ? $category : null),
                )),
            Tool::as('get_maintenance_backlog')
                ->for('Get maintenance request counts by status and priority')
                ->using(fn (): string => (string) json_encode($insights->maintenanceBacklog())),
            Tool::as('list_properties')
                ->for('List all properties in the organization by name')
                ->using(fn (): string => (string) json_encode($insights->properties())),
        ];

        $startedAt = microtime(true);

        try {
            $response = Prism::text()
                ->using($credential->prismProvider(), $credential->model)
                ->usingProviderConfig($credential->requestConfig())
                ->withSystemPrompt(
                    'You are the MaliManager assistant for the organization '
                    .$organization?->name.'. Answer questions about their portfolio using '
                    .'the provided tools only — never invent numbers. Today is '
                    .now()->toDateString().'. Be concise and concrete.',
                )
                ->withMaxSteps(5)
                ->withTools($tools)
                ->withPrompt($validated['question'])
                ->asText();

            $this->gateway->log(
                $credential,
                $request->user(),
                AiFeature::AskData,
                promptTokens: $response->usage->promptTokens,
                completionTokens: $response->usage->completionTokens,
                durationMs: (int) ((microtime(true) - $startedAt) * 1000),
            );

            return response()->json(['answer' => $response->text]);
        } catch (\Throwable $e) {
            $this->gateway->log(
                $credential,
                $request->user(),
                AiFeature::AskData,
                status: 'error',
                error: $e->getMessage(),
            );

            // Surface the upstream detail so credential owners can debug
            // their own keys (e.g. NVIDIA function-not-activated).
            return response()->json([
                'error' => 'The assistant could not answer right now. Please try again.',
                'detail' => $e->getMessage(),
            ], 502);
        }
    }

    private function resolve(Request $request)
    {
        return $this->gateway->resolve(
            $request->user(),
            AiFeature::AskData,
            TenancyContext::organization(),
        );
    }
}
