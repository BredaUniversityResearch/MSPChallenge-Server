<?php

namespace App\Domain\PolicyData;

use ReflectionException;
use Swaggest\JsonSchema\Context;
use Swaggest\JsonSchema\InvalidValue;
use Swaggest\JsonSchema\Schema;

abstract class ItemsPolicyDataBase extends PolicyDataBase
{
    /** @var object[] */
    public array $items;

    abstract public function getItemSchema(): Schema;

    /**
     * @return array
     */
    private function getRequiredFilterClassNames(): array
    {
        $policyFilterClassNames = [];
        foreach ($this->getItemSchema()->allOf as $filterSchema) {
            if (!is_subclass_of($filterSchema->getObjectItemClass(), FilterBasePolicyData::class)) {
                continue;
            }
            $policyFilterClassNames[] = $filterSchema->getObjectItemClass();
        }
        return $policyFilterClassNames;
    }

    public function matchFiltersOn(object $otherItem): ?bool
    {
        $requiredFilterClassNames = $this->getRequiredFilterClassNames();
        if (empty($requiredFilterClassNames)) {
            return null; // no filters for this policy
        }
        if (empty($this->items)) {
            return false; // no filters to match
        }
        foreach ($this->items as $item) {
            $matches = [];
            foreach ($requiredFilterClassNames as $filterClassName) {
                try {
                    /** @var FilterBasePolicyData $data */
                    $data = call_user_func([$filterClassName, 'import'], $item);
                } catch (\Exception|InvalidValue $e) {
                    // data does not match the required schema
                    $this->log('Item: ' . json_encode($item), self::LOG_LEVEL_DEBUG);
                    $this->log('Tried to import item as: ' . FleetFilterPolicyData::class, self::LOG_LEVEL_DEBUG);
                    $this->log($e->getMessage(), self::LOG_LEVEL_DEBUG);
                    continue;
                }
                $match = $data->match($otherItem);
                $this->log(
                    'Filter '.$data->getFilterTypeName()->value.' detected, '.($match === false ? '*NO* ':'').
                    'match on: '.json_encode($otherItem).', with filter data: '.json_encode($item),
                    self::LOG_LEVEL_DEBUG
                );
                if ($match) {
                    $matches[$filterClassName] = true;
                }
            }
            if (count($matches) === count($requiredFilterClassNames)) {
                return true; // all filters matched
            }
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function import($data, Context $options = null)
    {
        $result = parent::import($data, $options);
        // should have been taken care of by the schema
        assert(property_exists($data, 'items') && is_array($data->items));
        $itemSchema = (new static())->getItemSchema();
        foreach ($data->items as $key => $item) {
            $itemSchema->in($item, $options); // this will throw an exception if the item does not match the schema
            // override the \Swaggest\JsonSchema\Structure\ObjectItem with just a stdClass objects
            //   such that its properties are still accessible
            $result->items[$key] = $item;
        }
        return $result;
    }

    /**
     * @inheritdoc
     * @throws ReflectionException
     */
    public static function setUpProperties($properties, Schema $ownerSchema): void
    {
        parent::setUpProperties($properties, $ownerSchema);
        $itemsSchema = Schema::arr();
        $itemsSchema->items = Schema::object()
            ->addMeta(
                fn() => (new static())->getItemSchema(),
                PolicyDataSchemaMetaName::FIELD_OBJECT_SCHEMA_CALLABLE->value
            );
        $properties->items = $itemsSchema;
    }
}
