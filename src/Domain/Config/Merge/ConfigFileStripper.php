<?php

namespace App\Domain\Config\Merge;

use App\Domain\Config\ConfigDirectory;
use App\Domain\Config\Split\ConfigValues;
use App\Domain\Config\Split\GenericNameRegistry;

/**
 * Plans and performs the stripping of config files: the generic part is removed from a config, so only the
 * region specific part stays in the file (see RegionConfigStripper).
 *
 * A file is only ever replaced by a version that has been written, read back and verified:
 * merging it with the generic config has to give the same config as merging the file as it is now.
 */
final class ConfigFileStripper
{
    private readonly RegionConfigMerger $merger;
    private readonly RegionConfigStripper $stripper;
    private readonly ConfigNormalizer $normalizer;
    private readonly ConfigComparator $comparator;

    public function __construct()
    {
        $this->merger = new RegionConfigMerger();
        $this->stripper = new RegionConfigStripper($this->merger);
        $this->normalizer = new ConfigNormalizer();
        $this->comparator = new ConfigComparator();
    }

    /**
     * @throws \JsonException
     */
    public function plan(
        string $id,
        string $path,
        \stdClass $current,
        \stdClass $generic,
        GenericNameRegistry $names,
        ?\stdClass $effective = null
    ): StripPlan {
        $warnings = [];
        // $effective: the final config of a file that was stripped against another generic config
        $effective ??= $this->merger->merge($generic, $current, $warnings);
        $result = $this->stripper->strip($generic, $effective, $names, true);
        $warnings = array_merge($warnings, $result->warnings);
        if (!$result->isStripped()) {
            return new StripPlan(
                $id,
                $path,
                StripPlan::REFUSED,
                $current,
                null,
                $result->reasons,
                $warnings,
                [],
                $effective
            );
        }
        $status = ConfigValues::canonical($result->region) === ConfigValues::canonical($current)
            ? StripPlan::UNCHANGED
            : StripPlan::CHANGED;
        return new StripPlan($id, $path, $status, $current, $result->region, [], $warnings, $result->stats, $effective);
    }

    /**
     * Replaces $target (by default the file itself) with the stripped config of a CHANGED plan. An UNCHANGED
     * plan is only written when $writeUnchanged is set (to put the complete result in another directory).
     *
     * @return string[] problems, empty when the file was replaced; on problems the file is untouched
     * @throws \JsonException
     */
    public function apply(
        StripPlan $plan,
        ConfigDirectory $directory,
        \stdClass $generic,
        ?string $target = null,
        bool $writeUnchanged = false
    ): array {
        [$problems, $temporary] = $this->stage($plan, $directory, $generic, $target, $writeUnchanged);
        if ($temporary !== null) {
            $directory->commit($temporary, $target ?? $plan->path);
        }
        return $problems;
    }

    /**
     * Writes the stripped config to a temporary file next to the target and verifies it, without replacing
     * anything yet (see ConfigDirectory::stage()).
     *
     * @return array{0: string[], 1: ?string} the problems, and the temporary file when there are none
     * @throws \JsonException
     */
    public function stage(
        StripPlan $plan,
        ConfigDirectory $directory,
        \stdClass $generic,
        ?string $target = null,
        bool $writeUnchanged = false
    ): array {
        $writable = $plan->status === StripPlan::CHANGED || ($plan->status === StripPlan::UNCHANGED && $writeUnchanged);
        if (!$writable || $plan->stripped === null) {
            return [['Nothing to write for ' . $plan->id . ' (' . $plan->status . ')'], null];
        }
        $expected = $this->normalizer->normalize($plan->effective ?? $this->merger->merge($generic, $plan->current));
        return $directory->stage(
            $target ?? $plan->path,
            $plan->stripped,
            fn(\stdClass $written): array => $this->comparator->differences(
                $expected,
                $this->merger->merge($generic, $written)
            )
        );
    }
}
