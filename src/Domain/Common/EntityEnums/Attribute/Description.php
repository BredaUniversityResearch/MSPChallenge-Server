<?php

namespace App\Domain\Common\EntityEnums\Attribute;

use Attribute;

#[Attribute]
class Description
{
    public function __construct(
        public string $description,
    ) {
    }
}
