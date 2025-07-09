<?php

namespace App\Entity\Mapping;

#[\Attribute]
class ReadonlyIDs
{
    public function __construct(
        /** @var int[] $readonlyIDs */
        public array $readonlyIDs = []
    ) {
    }
}
