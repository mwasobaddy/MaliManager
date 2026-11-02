<?php

namespace App\Actions\Inspections\Concerns;

use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

trait InspectionHelpers
{
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
