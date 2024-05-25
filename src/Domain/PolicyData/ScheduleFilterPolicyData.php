<?php

namespace App\Domain\PolicyData;

use App\Domain\Common\EntityEnums\PolicyFilterTypeName;
use Swaggest\JsonSchema\Schema;
use Swaggest\JsonSchema\Structure\ClassStructure;

class ScheduleFilterPolicyData extends ClassStructure
{
    /**
     * @var int[]
     */
    public array $months = [];

    /**
     * @var int[] $months
     */
    public function __construct(array $months = [])
    {
        $this->months = $months;
    }

    /**
     * @inheritdoc
     */
    public static function setUpProperties($properties, $ownerSchema)
    {
        $ownerSchema->addMeta(PolicyGroup::FILTER, PolicyDataMetaName::GROUP->value);
        $ownerSchema->addMeta(PolicyFilterTypeName::SCHEDULE, PolicyDataMetaName::TYPE_NAME->value);
        $ownerSchema->type = 'object';
        $monthsSchema = Schema::arr();
        $monthsSchema->items = Schema::integer();
        $properties->months = $monthsSchema;
        $ownerSchema->required = ['months'];
    }
}
