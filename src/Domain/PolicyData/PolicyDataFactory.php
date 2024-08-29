<?php

namespace App\Domain\PolicyData;

use App\Domain\Common\EntityEnums\PolicyTypeName;
use Swaggest\JsonSchema\Exception;
use Swaggest\JsonSchema\InvalidValue;
use Swaggest\JsonSchema\Wrapper;

class PolicyDataFactory
{
    public static function getPolicyDataSchemaByType(PolicyTypeName $typeName): Wrapper
    {
        return match ($typeName) {
            PolicyTypeName::BUFFER_ZONE => BufferZonePolicyData::schema(),
            PolicyTypeName::SEASONAL_CLOSURE => SeasonalClosurePolicyData::schema(),
            PolicyTypeName::ECO_GEAR => EcoGearPolicyData::schema(),
            PolicyTypeName::ENERGY_DISTRIBUTION => EnergyDistributionPolicyData::schema(),
            PolicyTypeName::SHIPPING_SAFETY_ZONES => ShippingSafetyZonesPolicyData::schema(),
            PolicyTypeName::FISHING_EFFORT => FishingEffortPolicyData::schema()
        };
    }

    public static function createPolicyDataByType(PolicyTypeName $policyTypeName): PolicyDataBase|ItemsPolicyDataBase
    {
        return match ($policyTypeName) {
            PolicyTypeName::BUFFER_ZONE => new BufferZonePolicyData(),
            PolicyTypeName::SEASONAL_CLOSURE => new SeasonalClosurePolicyData(),
            PolicyTypeName::ECO_GEAR => new EcoGearPolicyData(),
            PolicyTypeName::ENERGY_DISTRIBUTION => new EnergyDistributionPolicyData(),
            PolicyTypeName::SHIPPING_SAFETY_ZONES => new ShippingSafetyZonesPolicyData(),
            PolicyTypeName::FISHING_EFFORT => new FishingEffortPolicyData()
        };
    }

    /**
     * @throws Exception
     * @throws InvalidValue
     */
    public static function createPolicyDataByJsonObject(object $json): PolicyDataBase|ItemsPolicyDataBase
    {
        if (!property_exists($json, 'policy_type')) {
            throw new InvalidValue('Policy type is missing');
        }
        return match (PolicyTypeName::from($json->policy_type)) {
            PolicyTypeName::BUFFER_ZONE => BufferZonePolicyData::import($json),
            PolicyTypeName::SEASONAL_CLOSURE => SeasonalClosurePolicyData::import($json),
            PolicyTypeName::ECO_GEAR => EcoGearPolicyData::import($json),
            PolicyTypeName::ENERGY_DISTRIBUTION => EnergyDistributionPolicyData::import($json),
            PolicyTypeName::SHIPPING_SAFETY_ZONES => ShippingSafetyZonesPolicyData::import($json),
            PolicyTypeName::FISHING_EFFORT => FishingEffortPolicyData::import($json)
        };
    }
}
