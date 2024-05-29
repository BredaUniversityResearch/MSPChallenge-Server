<?php

namespace App\Domain\PolicyData;

use App\Domain\Common\EntityEnums\PolicyFilterTypeName;
use Swaggest\JsonSchema\Schema;
use Swaggest\JsonSchema\Structure\ClassStructure;

class ScheduleFilterPolicyData extends ClassStructure
{
    const DEFAULT_VALUE_MONTHS = 0;

    public int $months = self::DEFAULT_VALUE_MONTHS;

    /**
     * @inheritdoc
     */
    public static function setUpProperties($properties, $ownerSchema)
    {
        $ownerSchema->addMeta(PolicyGroup::FILTER, PolicyDataMetaName::GROUP->value);
        $ownerSchema->addMeta(PolicyFilterTypeName::SCHEDULE, PolicyDataMetaName::TYPE_NAME->value);
        $ownerSchema->type = 'object';
        $monthsSchema = Schema::integer()
            ->addMeta(true, PolicyDataMetaName::ON_INPUT_BITWISE_HANDLING->value)
            ->addMeta('Enter a month value between 1-12', PolicyDataMetaName::ON_INPUT_DESCRIPTION->value);
        $monthsSchema->default = self::DEFAULT_VALUE_MONTHS;
        $properties->months = $monthsSchema;
        $ownerSchema->required = ['months'];
    }
}
