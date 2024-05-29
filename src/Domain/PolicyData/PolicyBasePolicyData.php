<?php

namespace App\Domain\PolicyData;

use Swaggest\JsonSchema\Schema;
use Swaggest\JsonSchema\Structure\ClassStructure;

abstract class PolicyBasePolicyData extends ClassStructure
{
    public string $policy_type;

    /** @var object[] */
    public array $items;

    public function __construct(string $policy_type)
    {
        $this->policy_type = $policy_type;
    }

    /**
     * @inheritdoc
     */
    public static function setUpProperties($properties, Schema $ownerSchema): void
    {
        $ownerSchema->addMeta(PolicyGroup::POLICY, PolicyDataMetaName::GROUP->value);
        $ownerSchema->type = 'object';
        $ownerSchema->required = ['policy_type', 'items'];
        $ownerSchema->additionalProperties = true; // we allow additional properties
        $properties->policy_type = Schema::string();
        $itemsSchema = Schema::arr();
        $itemsSchema->items = Schema::object();
        $properties->items = $itemsSchema;
    }
}
