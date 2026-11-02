<?php

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\InspectionHelpers;
use App\Enums\AiFeature;
use App\Models\Inspection;
use App\Models\User;
use App\Services\Service;
use App\Support\Ai\AiGateway;
use Illuminate\Support\Facades\Storage;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Messages\UserMessage;

class GenerateInspectionReport extends Service
{
    use InspectionHelpers;

    public function __construct(private AiGateway $gateway) {}

    public function handle(Inspection $inspection, User $inspector): array
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
                ->withSchema($this->reportSchema())
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
}
