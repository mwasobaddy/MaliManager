<?php

namespace App\Enums;

/**
 * AI capabilities that can be individually enabled on a credential and
 * gated in the UI. Values are stable API-ish keys stored in JSON columns.
 */
enum AiFeature: string
{
    case AskData = 'ask_data';
    case MaintenanceTriage = 'maintenance_triage';
    case ContentDrafting = 'content_drafting';
    case PredictiveFlags = 'predictive_flags';
    case InspectionReports = 'inspection_reports';

    public function label(): string
    {
        return match ($this) {
            self::AskData => 'Ask-your-data assistant',
            self::MaintenanceTriage => 'Maintenance triage',
            self::ContentDrafting => 'Content drafting',
            self::PredictiveFlags => 'Predictive flags',
            self::InspectionReports => 'Inspection reports',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $feature) => ['value' => $feature->value, 'label' => $feature->label()],
            self::cases(),
        );
    }
}
