<?php

namespace App\Domain\Config\Split;

final readonly class SplitResult
{
    /**
     * @param array<string, \stdClass> $regions config id => region specific document
     * @param array<string, int> $layerOverrides layer field => number of region layer entries overriding it
     * @param array<string, array{of: int, best: int, distinct: int, generic: bool, kind: string}> $support
     *        per leaf value in the simulation/dependency sections: how many configs agree on the most common value
     * @param array<string, int> $dropped what was removed on purpose, with counts
     * @param string[] $warnings
     */
    public function __construct(
        public \stdClass $generic,
        public array     $regions,
        public int       $genericLayerCount,
        public int       $sharedLayerCount,
        public array     $layerOverrides,
        public array     $support,
        public array     $dropped,
        public array     $warnings,
        public int       $regionOnlyLayerCount = 0
    ) {
    }
}
