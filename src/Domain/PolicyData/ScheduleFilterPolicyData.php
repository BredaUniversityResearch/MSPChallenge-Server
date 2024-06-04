<?php

namespace App\Domain\PolicyData;

use App\Domain\Common\EntityEnums\PolicyFilterTypeName;
use ReflectionClass;
use ReflectionProperty;
use Swaggest\JsonSchema\Schema;
use Swaggest\JsonSchema\Structure\ClassStructure;

class ScheduleFilterPolicyData extends FilterBasePolicyData
{
    const DEFAULT_VALUE_MONTHS = 0;

    public int $months = self::DEFAULT_VALUE_MONTHS;

    public static function createFromGameMonth(int $month): self
    {
        $instance = new self();
        $monthNumeric = ($month % 12) + 1;
        $instance->months = pow(2, $monthNumeric - 1);
        return $instance;
    }

    public function getFilterTypeName(): PolicyFilterTypeName
    {
        return PolicyFilterTypeName::SCHEDULE;
    }

    public function match(object $otherItem): bool
    {
        if (!property_exists($otherItem, 'months')) {
            return true; // no "filter", so just match.
        }
        if (!is_int($otherItem->months)) {
            return false; // there seems to be a setup for a filter, but the value is not an integer, so do not match
        }
        return ($this->months & $otherItem->months) == $otherItem->months;
    }

    /**
     * @inheritdoc
     */
    public static function setUpProperties($properties, $ownerSchema): void
    {
        parent::setUpProperties($properties, $ownerSchema);
        $ownerSchema->addMeta(PolicyFilterTypeName::SCHEDULE, PolicyDataSchemaMetaName::POLICY_TYPE_NAME->value);
        $monthsSchema = Schema::integer()
            ->addMeta(true, PolicyDataSchemaMetaName::FIELD_ON_INPUT_BITWISE_HANDLING->value)
            ->addMeta('Enter a month value between 1-12 (or 0 = none)', PolicyDataSchemaMetaName::FIELD_ON_INPUT_DESCRIPTION->value);
        $monthsSchema->default = self::DEFAULT_VALUE_MONTHS;
        $properties->months = $monthsSchema;
    }
}
