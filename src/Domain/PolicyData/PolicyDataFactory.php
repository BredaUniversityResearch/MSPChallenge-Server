<?php

namespace App\Domain\PolicyData;

use App\Domain\Common\EntityEnums\PolicyTypeName;
use Swaggest\JsonSchema\Exception;
use Swaggest\JsonSchema\InvalidValue;

class PolicyDataFactory
{
    public static function createPolicyDataByType(
        PolicyTypeName $policyTypeName
    ): PolicyBasePolicyData|BufferZonePolicyData|SeasonalClosurePolicyData|EcoGearPolicyData {
        return match ($policyTypeName) {
            PolicyTypeName::BUFFER_ZONE => new BufferZonePolicyData(),
            PolicyTypeName::SEASONAL_CLOSURE => new SeasonalClosurePolicyData(),
            PolicyTypeName::ECO_GEAR => new EcoGearPolicyData()
        };
    }

    /**
     * @throws Exception
     * @throws InvalidValue
     */
    public static function createPolicyDataByJsonObject(
        object $json
    ): PolicyBasePolicyData|BufferZonePolicyData|SeasonalClosurePolicyData|EcoGearPolicyData {
        if (!property_exists($json, 'type')) {
            throw new InvalidValue('Policy type is missing');
        }
        return match (PolicyTypeName::from($json->type)) {
            PolicyTypeName::BUFFER_ZONE => BufferZonePolicyData::import($json),
            PolicyTypeName::SEASONAL_CLOSURE => SeasonalClosurePolicyData::import($json),
            PolicyTypeName::ECO_GEAR => EcoGearPolicyData::import($json)
        };
    }
}
