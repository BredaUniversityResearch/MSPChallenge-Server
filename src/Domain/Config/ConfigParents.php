<?php

namespace App\Domain\Config;

use App\Domain\Config\Merge\RegionConfigMerger;
use App\Domain\Config\Split\GenericNameRegistry;

/**
 * Follows the parents of a config.
 *
 * A config names its parent in metadata.parent: the name of a generic config, a file <name>.json in the root of the
 * config folder. A generic config can have a parent too, so there can be a chain: for example a base config that
 * every config uses, a public one with the layers of the public regions, and a restricted one with the layers of the
 * restricted regions, each of them the parent of the regions it serves and of nothing else.
 *
 * The pool of a config is its parents merged, from the root down to the nearest one, which is what the config itself
 * is merged with (RegionConfigMerger::merge()). A config without a parent has an empty pool, it has to be complete.
 *
 * Only generic configs can be parents (every layer has an msp_config_generic_name): they hold layers that others
 * choose from. An instance is meant for one run of a command: it remembers the pools it made.
 */
final class ConfigParents
{
    public const int MAX_DEPTH = 8;

    /** @var array<string, \stdClass> */
    private array $pools = [];
    private readonly RegionConfigMerger $merger;

    /**
     * @param \Closure(string): ?\stdClass $load the decoded generic config of a name, null when there is none
     */
    public function __construct(private readonly \Closure $load, ?RegionConfigMerger $merger = null)
    {
        $this->merger = $merger ?? new RegionConfigMerger();
    }

    public static function fromDirectory(ConfigDirectory $directory): self
    {
        return new self(static function (string $name) use ($directory): ?\stdClass {
            // a messenger worker lives long: do not trust what PHP remembers about a file that may have changed
            clearstatcache(true, $directory->parentPath($name));
            if (!$directory->hasGeneric($name)) {
                return null;
            }
            try {
                return $directory->read($directory->parentPath($name));
            } catch (\JsonException | \RuntimeException $e) {
                throw new ConfigParentException(
                    sprintf('The parent file %s cannot be used: %s', $directory->parentPath($name), $e->getMessage())
                );
            }
        });
    }

    /**
     * @return ?string the name of the parent of a config, null when it has none
     * @throws ConfigParentException when the name is not a valid file name
     */
    public static function parentOf(\stdClass $config): ?string
    {
        $parent = ($config->metadata ?? null) instanceof \stdClass ? ($config->metadata->parent ?? null) : null;
        if ($parent === null) {
            return null;
        }
        if (!is_string($parent) || !ConfigDirectory::isValidParentName($parent)) {
            throw new ConfigParentException(
                'metadata.parent has to be the name of a file without the extension, letters, digits, _ and - '
                . 'only: ' . json_encode($parent)
            );
        }
        return $parent;
    }

    /**
     * What a config is merged with: its parents merged. Empty when it has no parent.
     *
     * @throws ConfigParentException
     */
    public function poolOf(\stdClass $config, string $label = 'the config'): \stdClass
    {
        $parent = self::parentOf($config);
        if ($parent === null) {
            if (self::needsParent($config)) {
                throw new ConfigParentException(sprintf(
                    '%s has layers that refer to a generic layer (msp_config_generic_name), but no metadata.parent '
                    . 'that says which generic config has them.',
                    ucfirst($label)
                ));
            }
            return $this->emptyPool();
        }
        return $this->poolOfParent($parent, $label);
    }

    /**
     * Whether a config only makes sense with a parent: a layer refers to a generic layer.
     */
    public static function needsParent(\stdClass $config): bool
    {
        $layers = ($config->datamodel ?? null) instanceof \stdClass ? ($config->datamodel->meta ?? []) : [];
        foreach (is_array($layers) ? $layers : [] as $layer) {
            if ($layer instanceof \stdClass && isset($layer->msp_config_generic_name)) {
                return true;
            }
        }
        return false;
    }

    /**
     * The layer names of a generic config itself (not of its parents): generic name => layer names.
     *
     * @return array<string, string[]>
     */
    public static function nameMapOf(\stdClass $generic): array
    {
        $map = [];
        $names = $generic->layer_names ?? null;
        foreach ($names instanceof \stdClass ? get_object_vars($names) : [] as $genericName => $layerNames) {
            $map[(string)$genericName] = is_array($layerNames) ? array_values(array_map('strval', $layerNames)) : [];
        }
        return $map;
    }

    /**
     * The pool of a generic config and its own parents.
     *
     * @throws ConfigParentException
     */
    public function poolOfParent(string $name, string $label = 'the config'): \stdClass
    {
        if (isset($this->pools[$name])) {
            return $this->pools[$name];
        }
        $pool = null;
        foreach ($this->chain($name, $label) as $generic) {
            $pool = $pool === null ? $generic : $this->merger->mergeGeneric($pool, $generic);
        }
        return $this->pools[$name] = $pool ?? $this->emptyPool();
    }

    /**
     * The layer names of the generic layers of a generic config and its parents: generic name => layer names.
     *
     * @return array<string, string[]>
     * @throws ConfigParentException
     */
    public function nameMapOfParent(string $name, string $label = 'the config'): array
    {
        $map = [];
        foreach ($this->chain($name, $label) as $generic) {
            foreach (self::nameMapOf($generic) as $genericName => $layerNames) {
                $map[$genericName] = array_values(array_unique(array_merge($map[$genericName] ?? [], $layerNames)));
            }
        }
        return $map;
    }

    public function namesOfParent(string $name, string $label = 'the config'): GenericNameRegistry
    {
        return GenericNameRegistry::fromMap($this->nameMapOfParent($name, $label));
    }

    /**
     * The generic configs of a parent and its parents, from the root down to the parent itself.
     *
     * @return array<string, \stdClass> name => the generic config
     * @throws ConfigParentException when one is missing or no generic config, or when they loop
     */
    public function chain(string $name, string $label = 'the config'): array
    {
        $chain = [];
        $current = $name;
        $from = $label;
        while ($current !== null) {
            if (isset($chain[$current])) {
                throw new ConfigParentException(sprintf(
                    'The parents of %s loop: %s',
                    $label,
                    implode(' -> ', array_merge(array_keys($chain), [$current]))
                ));
            }
            if (count($chain) >= self::MAX_DEPTH) {
                throw new ConfigParentException(
                    sprintf('%s has more than %d levels of parents.', ucfirst($label), self::MAX_DEPTH)
                );
            }
            $generic = ($this->load)($current) ?? throw new ConfigParentException(sprintf(
                'The parent "%s" of %s was not found: %s.json is needed.',
                $current,
                $from,
                $current
            ));
            $this->assertGeneric($current, $generic);
            $chain[$current] = $generic;
            $from = '"' . $current . '"';
            $current = self::parentOf($generic);
        }
        return array_reverse($chain);
    }

    private function assertGeneric(string $name, \stdClass $generic): void
    {
        $layers = ($generic->datamodel ?? null) instanceof \stdClass ? ($generic->datamodel->meta ?? []) : [];
        foreach (is_array($layers) ? $layers : [] as $index => $layer) {
            if (!$layer instanceof \stdClass || !isset($layer->msp_config_generic_name)) {
                throw new ConfigParentException(sprintf(
                    '"%s" cannot be a parent: layer %d has no msp_config_generic_name (a parent only holds generic '
                    . 'layers).',
                    $name,
                    $index
                ));
            }
        }
    }

    private function emptyPool(): \stdClass
    {
        $pool = new \stdClass();
        $pool->datamodel = new \stdClass();
        $pool->datamodel->meta = [];
        return $pool;
    }
}
