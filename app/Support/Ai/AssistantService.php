<?php

namespace App\Support\Ai;

use App\Enums\AiFeature;
use App\Exceptions\AssistantUnavailableException;
use App\Models\Inspection;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\AiRenewalService;
use App\Services\DraftingService;
use App\Services\InspectionService;
use App\Services\MaintenanceTriageService;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

/**
 * The unified, agentic ask-your-data assistant. One implementation powers
 * the org, occupant and platform surfaces — only the injected Scope and
 * credential differ. The LLM drives a small set of safe tools
 * (describe_data / query_data / search_text) plus optional specialised
 * features; it never sees the database, only the allowlisted query results.
 */
final class AssistantService
{
    public function __construct(
        private AiGateway $gateway,
        private DataQueryService $queries,
        private TextSearchService $search,
        private DraftingService $drafting,
        private MaintenanceTriageService $triage,
        private InspectionService $inspections,
        private AiRenewalService $renewals,
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{answer: string, artifacts: array}
     */
    public function ask(
        User $user,
        Scope $scope,
        string $question,
        array $history = [],
        ?ResolvedAiCredential $credential = null,
    ): array {
        $credential ??= $this->resolveCredential($user, $scope);

        if ($credential === null) {
            throw new AssistantUnavailableException('AI is not configured for your account.');
        }

        $artifacts = [];

        $tools = [
            Tool::as('describe_data')
                ->for('List the entities and fields you are allowed to query. Call this first if you are unsure what data exists.')
                ->using(fn (): string => json_encode(SemanticLayer::catalogFor($scope->type))),
            Tool::as('query_data')
                ->for('Run a read-only analytics query against one entity. Returns grouped/aggregated rows you can summarise or chart.')
                ->withObjectParameter('query', 'Analytics query specification', $this->querySchema(), ['entity'])
                ->using(function ($query) use ($scope, &$artifacts): string {
                    return $this->queryTool($this->toolPayload($query, 'query'), $scope, $artifacts);
                }),
            Tool::as('search_text')
                ->for('Search free-text columns (notes, descriptions, titles) of an entity for a term.')
                ->withObjectParameter('search', 'Text search specification', $this->searchSchema(), ['entity', 'term'])
                ->using(function ($search) use ($scope, &$artifacts): string {
                    return $this->searchTool($this->toolPayload($search, 'search'), $scope, $artifacts);
                }),
        ];

        // Specialised features are only exposed inside an organization scope.
        if ($scope->type === Scope::ORG) {
            $tools = array_merge($tools, $this->specialisedTools($user, $scope, $artifacts));
        }

        $messages = [];

        foreach (array_slice($history, -6) as $turn) {
            $messages[] = $turn['role'] === 'assistant'
                ? new AssistantMessage((string) $turn['content'])
                : new UserMessage((string) $turn['content']);
        }

        $messages[] = new UserMessage($question);

        $startedAt = microtime(true);

        $response = null;
        $lastError = null;

        // Retry transient failures (free-tier rate limits, timeouts, 5xx)
        // so the agentic loop survives the throttling common on hosted NIM.
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = Prism::text()
                    ->using($credential->prismProvider(), $credential->model)
                    ->usingProviderConfig($credential->requestConfig())
                    ->withSystemPrompt($this->systemPrompt($scope))
                    ->withMaxSteps(8)
                    ->withTools($tools)
                    ->withMessages($messages)
                    ->asText();

                break;
            } catch (\Throwable $e) {
                $lastError = $e;

                if (! $this->isRetriablePrismError($e) || $attempt >= 3) {
                    break;
                }

                usleep(1_500_000 * $attempt);
            }
        }

        if ($response === null) {
            $this->gateway->log(
                $credential,
                $user,
                AiFeature::AskData,
                status: 'error',
                error: $lastError?->getMessage() ?? 'unknown',
            );

            throw new AssistantUnavailableException('The assistant could not answer right now. Please try again.', 0, $lastError);
        }

        $this->gateway->log(
            $credential,
            $user,
            AiFeature::AskData,
            promptTokens: $response->usage->promptTokens,
            completionTokens: $response->usage->completionTokens,
            durationMs: (int) ((microtime(true) - $startedAt) * 1000),
        );

        return ['answer' => $response->text, 'artifacts' => $artifacts];
    }

    /**
     * @param  array<int, array>  $artifacts
     * @return list<Tool>
     */
    private function specialisedTools(User $user, Scope $scope, array &$artifacts): array
    {
        $orgId = $scope->organizationId;

        return [
            Tool::as('draft_communication')
                ->for('Draft a tenant communication (rent reminder, notice, listing). Returns copy-ready text.')
                ->withObjectParameter('draft', 'Draft specification', $this->draftSchema(), ['type'])
                ->using(function ($draft) use ($user): string {
                    $draft = $this->toolPayload($draft, 'draft');

                    if (! $this->gateway->canUse($user, AiFeature::ContentDrafting)) {
                        return json_encode(['error' => 'Content drafting is not enabled for your account.']);
                    }

                    try {
                        $text = $this->drafting->generate(
                            $user,
                            (string) $draft['type'],
                            $draft['tone'] ?? null,
                            (array) ($draft['fields'] ?? []),
                        );
                    } catch (AssistantUnavailableException $e) {
                        return json_encode(['error' => $e->getMessage()]);
                    }

                    return json_encode(['draft' => $text]);
                }),
            Tool::as('triage_maintenance')
                ->for('Suggest a priority for a maintenance request. Updates its advisory ai_priority.')
                ->withObjectParameter('triage', 'Triage specification', $this->idSchema('request_id', 'Maintenance request id'), ['request_id'])
                ->using(function ($triage) use ($user, $orgId): string {
                    $triage = $this->toolPayload($triage, 'triage');

                    if (! $this->gateway->canUse($user, AiFeature::MaintenanceTriage)) {
                        return json_encode(['error' => 'Maintenance triage is not enabled for your account.']);
                    }

                    $request = MaintenanceRequest::query()->where('organization_id', $orgId)->find((int) $triage['request_id']);

                    if ($request === null) {
                        return json_encode(['error' => 'Maintenance request not found.']);
                    }

                    try {
                        $result = $this->triage->suggest($request, $user);
                    } catch (AssistantUnavailableException $e) {
                        return json_encode(['error' => $e->getMessage()]);
                    }

                    return json_encode($result);
                }),
            Tool::as('generate_inspection_report')
                ->for('Generate an AI condition report for an inspection.')
                ->withObjectParameter('report', 'Report specification', $this->idSchema('inspection_id', 'Inspection id'), ['inspection_id'])
                ->using(function ($report) use ($user, $orgId): string {
                    $report = $this->toolPayload($report, 'report');

                    if (! $this->gateway->canUse($user, AiFeature::InspectionReports)) {
                        return json_encode(['error' => 'Inspection reports are not enabled for your account.']);
                    }

                    $inspection = Inspection::query()->where('organization_id', $orgId)->find((int) $report['inspection_id']);

                    if ($inspection === null) {
                        return json_encode(['error' => 'Inspection not found.']);
                    }

                    try {
                        $result = $this->inspections->generateReport($inspection, $user);
                    } catch (AssistantUnavailableException $e) {
                        return json_encode(['error' => $e->getMessage()]);
                    }

                    return json_encode($result);
                }),
            Tool::as('predict_renewals')
                ->for('Predict renewal likelihood / risk for a specific lease.')
                ->withObjectParameter('renewal', 'Renewal specification', $this->idSchema('lease_id', 'Lease id'), ['lease_id'])
                ->using(function ($renewal) use ($user, $orgId): string {
                    $renewal = $this->toolPayload($renewal, 'renewal');

                    if (! $this->gateway->canUse($user, AiFeature::PredictiveFlags)) {
                        return json_encode(['error' => 'Predictive flags are not enabled for your account.']);
                    }

                    $lease = Lease::query()->where('organization_id', $orgId)->find((int) $renewal['lease_id']);

                    if ($lease === null || $lease->property === null) {
                        return json_encode(['error' => 'Lease not found.']);
                    }

                    try {
                        $result = $this->renewals->suggest($user, $lease->property, $lease);
                    } catch (AssistantUnavailableException $e) {
                        return json_encode(['error' => $e->getMessage()]);
                    }

                    return json_encode($result);
                }),
        ];
    }

    private function resolveCredential(User $user, Scope $scope): ?ResolvedAiCredential
    {
        return $scope->type === Scope::PLATFORM
            ? $this->gateway->resolvePlatform($user, AiFeature::AskData)
            : $this->gateway->resolve($user, AiFeature::AskData, $scope->organization());
    }

    private function systemPrompt(Scope $scope): string
    {
        $date = now()->toDateString();

        if ($scope->type === Scope::PLATFORM) {
            return 'You are the MaliManager platform assistant for administrators. You can analyse data '
                .'across ALL organizations on the platform using the provided tools only — never invent '
                ."numbers. Today is {$date}. Be concise, concrete and use charts when the data is grouped. "
                .$this->stopInstruction();
        }

        if ($scope->type === Scope::PERSON) {
            return "You are the MaliManager tenant assistant. Answer questions about the signed-in tenant's "
                .'OWN leases and maintenance requests using the tools only — never invent details. Today is '
                ."{$date}. Be concise. ".$this->stopInstruction();
        }

        $org = $scope->organization()?->name ?? 'this organization';

        return "You are the MaliManager assistant for the organization {$org}. Answer questions about THEIR "
            ."portfolio using the provided tools only — never invent numbers. Today is {$date}. Be concise, "
            .'concrete and present grouped data as a chart when useful. '.$this->stopInstruction();
    }

    /**
     * Strong instruction to terminate the agentic loop once enough data is
     * gathered — some models otherwise keep issuing tool calls and never
     * produce a final answer.
     */
    private function stopInstruction(): string
    {
        return 'Use the tools to gather what you need, then answer in the SAME response and make NO further '
            .'tool calls. Prefer 1-3 tool calls total; never repeat a query you have already run.';
    }

    /**
     * Transient provider errors worth retrying: hosted NIM free tiers throttle
     * (rate limits) and occasionally drop requests (timeouts, 502/504).
     */
    private function isRetriablePrismError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'rate limit')
            || str_contains($message, 'timed out')
            || str_contains($message, 'curl error 28')
            || str_contains($message, 'timeout')
            || str_contains($message, '502')
            || str_contains($message, '504');
    }

    private function toArtifact(array $result, string $kind): array
    {
        return [
            'kind' => $kind,
            'chart' => $this->chartHint($result, $kind),
            'entity' => $result['entity'],
            'group_by' => $result['group_by'] ?? [],
            'measures' => $result['measures'] ?? [['type' => 'count']],
            'rows' => $result['rows'],
            'row_count' => $result['row_count'],
        ];
    }

    /**
     * @param  array{entity: string, group_by?: array}  $result
     */
    private function chartHint(array $result, string $kind): ?string
    {
        if ($kind === 'search') {
            return 'table';
        }

        $groupBy = $result['group_by'] ?? [];

        if ($groupBy === []) {
            return null;
        }

        try {
            $dates = SemanticLayer::entity($result['entity'])['date_columns'];
        } catch (\Throwable $e) {
            $dates = [];
        }

        if (in_array($groupBy[0], $dates, true)) {
            return 'line';
        }

        if (count($groupBy) === 1 && ($result['row_count'] ?? 0) <= 8) {
            $measure = $result['measures'][0]['type'] ?? null;

            if (in_array($measure, ['count', 'sum'], true)) {
                return 'pie';
            }
        }

        return 'bar';
    }

    /**
     * Prism invokes a tool with the full arguments object (e.g. the model
     * emits `{"query": {...}}`); extract the named nested payload so the
     * handler receives the inner specification rather than the wrapper.
     *
     * @return array<string, mixed>
     */
    private function toolPayload(mixed $args, string $key): array
    {
        $args = is_object($args) ? (array) $args : (array) $args;

        return $args[$key] ?? $args;
    }

    private function queryTool(array $query, Scope $scope, array &$artifacts): string
    {
        try {
            $result = $this->queries->run($query, $scope);
        } catch (AssistantUnavailableException $e) {
            return json_encode(['error' => $e->getMessage()]);
        } catch (\InvalidArgumentException $e) {
            return json_encode(['error' => 'Invalid query: '.$e->getMessage()]);
        } catch (\Throwable $e) {
            return json_encode(['error' => 'The query could not be run.']);
        }

        $artifacts[] = $this->toArtifact($result, 'query');

        return json_encode($result);
    }

    private function searchTool(array $search, Scope $scope, array &$artifacts): string
    {
        try {
            $result = $this->search->search(
                $search['entity'] ?? '',
                (string) ($search['term'] ?? ''),
                $scope,
                (int) ($search['limit'] ?? 20),
            );
        } catch (AssistantUnavailableException $e) {
            return json_encode(['error' => $e->getMessage()]);
        } catch (\InvalidArgumentException $e) {
            return json_encode(['error' => 'Invalid search: '.$e->getMessage()]);
        } catch (\Throwable $e) {
            return json_encode(['error' => 'The search could not be run.']);
        }

        $artifacts[] = $this->toArtifact($result, 'search');

        return json_encode($result);
    }

    /**
     * @return array<int, Schema>
     */
    private function querySchema(): array
    {
        return [
            new StringSchema('entity', 'Entity name from describe_data, e.g. units, leases, expenses'),
            new ArraySchema('measures', 'Measures to compute (defaults to count)', new ObjectSchema('measure', 'One measure to compute', [
                new EnumSchema('type', 'count | sum | avg', ['count', 'sum', 'avg']),
                new StringSchema('column', 'Numeric column for sum/avg (omit for count)'),
            ], ['type'])),
            new ArraySchema('group_by', 'Dimensions to group by', new StringSchema('dim', 'a dimension name')),
            new ArraySchema('filters', 'Optional equality/range filters', new ObjectSchema('filter', 'filter', [
                new StringSchema('field', 'dimension/column'),
                new EnumSchema('op', 'operator', ['=', '!=', 'in', '>=', '<=', '>', '<']),
                new StringSchema('value', 'filter value'),
            ], ['field', 'op', 'value'])),
            new ObjectSchema('time_range', 'Optional date range', [
                new StringSchema('field', 'a date column'),
                new StringSchema('from', 'YYYY-MM-DD'),
                new StringSchema('to', 'YYYY-MM-DD'),
            ], []),
            new NumberSchema('limit', 'Max rows (default 100, max 500)', false),
        ];
    }

    /**
     * @return array<int, Schema>
     */
    private function searchSchema(): array
    {
        return [
            new StringSchema('entity', 'Entity name from describe_data'),
            new StringSchema('term', 'Term to search for'),
            new NumberSchema('limit', 'Max rows (default 20, max 50)', false),
        ];
    }

    /**
     * @return array<int, Schema>
     */
    private function draftSchema(): array
    {
        return [
            new EnumSchema('type', 'Communication type', [
                'rent_reminder', 'lease_expiry_notice', 'move_out_letter', 'listing_description',
            ]),
            new EnumSchema('tone', 'Tone', ['formal', 'friendly']),
            new ObjectSchema('fields', 'Key/value details to include (tenant_name, amount, due_date, ...)', [], [], true),
        ];
    }

    /**
     * @return array<int, Schema>
     */
    private function idSchema(string $name, string $description): array
    {
        return [new NumberSchema($name, $description, false)];
    }
}
