<?php

namespace App\Services;

use App\Enums\AiFeature;
use App\Models\Inspection;
use App\Models\Property;
use App\Models\User;
use App\Support\Ai\AiGateway;
use App\Support\TenancyContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Messages\UserMessage;

/**
 * Unit inspections: creation with photo attachments, and AI-generated
 * condition reports from those photos plus inspector notes. Report
 * generation resolves the caller's BYO credential through AiGateway and
 * logs usage; failures propagate to the controller for toast handling.
 */
class InspectionService extends Service
{
    public function __construct(private AiGateway $gateway) {}

    /**
     * @param  array<string, mixed>  $data  validated request payload
     * @param  list<UploadedFile>  $photos
     */
    public function create(Property $property, User $actor, array $data, array $photos = []): Inspection
    {
        return $this->transaction(function () use ($property, $actor, $data, $photos) {
            $inspection = new Inspection([
                'tenant_id' => TenancyContext::tenantId(),
                'organization_id' => $property->organization_id,
                'title' => $data['title'],
                'inspection_date' => $data['inspection_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);
            $inspection->property_id = $property->id;
            $inspection->unit_id = (int) $data['unit_id'];

            $this->save($inspection);

            foreach (array_slice($photos, 0, Inspection::MAX_PHOTOS) as $photo) {
                /** @var UploadedFile $photo */
                $inspection->addMedia($photo)->toMediaCollection('photos');
            }

            return $inspection;
        });
    }

    public function softDelete(Inspection $inspection): void
    {
        $this->transaction(function () use ($inspection): void {
            $this->delete($inspection);
        });
    }

    /**
     * Generate the structured condition report from photos + notes using
     * the caller's BYO credential (vision input). Usage is logged; the
     * report is stored on the inspection.
     *
     * @return array<string, mixed> the generated report
     */
    public function generateReport(Inspection $inspection, User $inspector): array
    {
        $organization = $inspection->organization;

        $credential = $this->gateway->resolve(
            $inspector,
            AiFeature::InspectionReports,
            $organization,
        );

        if ($credential === null) {
            throw new \RuntimeException('AI is not configured for your account.');
        }

        $images = $inspection->getMedia('photos')
            ->filter(fn ($media) => str_starts_with((string) $media->mime_type, 'image/'))
            ->map(function ($media) {
                // stream() can be null under some disk-resolution
                // conditions; fall back to a direct disk read.
                $stream = $media->stream();
                $content = is_resource($stream)
                    ? stream_get_contents($stream)
                    : null;

                if (! is_string($content) || $content === '') {
                    $content = Storage::disk($media->disk)
                        ->get($media->getPathRelativeToRoot());
                }

                return Image::fromRawContent((string) $content);
            })
            ->values()
            ->all();

        if ($images === []) {
            throw new \RuntimeException('Add at least one photo first.');
        }

        $prompt = "Inspection of unit: {$inspection->unit?->name}. "
            .'Inspector notes: '.($inspection->notes ?? 'none provided').'.'
            .' Analyze the attached photos in order and produce the structured report.';

        $startedAt = microtime(true);

        try {
            $response = Prism::structured()
                ->using($credential->prismProvider(), $credential->model)
                ->usingProviderConfig($credential->requestConfig())
                ->withSchema(self::reportSchema())
                ->withSystemPrompt(
                    'You produce property inspection reports for a Kenyan property '
                    .'manager. For each photo, identify the area shown and its condition; '
                    .'flag any damage or issues found; end with actionable recommendations.',
                )
                ->withMessages([new UserMessage($prompt, $images)])
                ->asStructured();

            $inspection->update([
                'ai_report' => $response->structured,
                'report_generated_at' => now(),
            ]);

            $this->gateway->log(
                $credential,
                $inspector,
                AiFeature::InspectionReports,
                promptTokens: $response->usage->promptTokens,
                completionTokens: $response->usage->completionTokens,
                durationMs: (int) ((microtime(true) - $startedAt) * 1000),
            );

            return $response->structured;
        } catch (\Throwable $e) {
            $this->gateway->log(
                $credential,
                $inspector,
                AiFeature::InspectionReports,
                status: 'error',
                error: $e->getMessage(),
            );

            throw $e;
        }
    }

    private static function reportSchema(): ObjectSchema
    {
        return new ObjectSchema(
            name: 'inspection_report',
            description: 'Structured property inspection report',
            properties: [
                new StringSchema('overall_condition', 'Overall condition: excellent, good, fair or poor'),
                new ArraySchema(
                    'areas',
                    'One entry per inspected area/photo',
                    new ObjectSchema(
                        'area',
                        'A single inspected area',
                        [
                            new StringSchema('area', 'Area name (e.g. Kitchen, Bathroom)'),
                            new StringSchema('condition', 'Condition: excellent, good, fair or poor'),
                            new StringSchema('issues', 'Issues observed, or none'),
                        ],
                        ['area', 'condition', 'issues'],
                    ),
                ),
                new ArraySchema(
                    'recommendations',
                    'Actionable follow-up recommendations',
                    new StringSchema('recommendation', 'A recommended action'),
                ),
            ],
            requiredFields: ['overall_condition', 'areas', 'recommendations'],
        );
    }
}
