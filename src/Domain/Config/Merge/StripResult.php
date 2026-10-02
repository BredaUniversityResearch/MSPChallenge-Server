<?php

namespace App\Domain\Config\Merge;

final readonly class StripResult
{
    /**
     * @param string[] $reasons why the config could not be stripped (only when $region is null)
     * @param string[] $warnings
     * @param array<string, int> $stats
     */
    private function __construct(
        public ?\stdClass $region,
        public array      $reasons,
        public array      $warnings,
        public array      $stats
    ) {
    }

    public static function stripped(\stdClass $region, array $stats, array $warnings): self
    {
        return new self($region, [], $warnings, $stats);
    }

    public static function failed(array $reasons, array $warnings): self
    {
        return new self(null, $reasons, $warnings, []);
    }

    public function isStripped(): bool
    {
        return $this->region !== null;
    }
}
