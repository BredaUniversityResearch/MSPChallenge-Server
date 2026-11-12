<?php

namespace App\Domain\Config\Split;

/**
 * Maps region specific layer names (e.g. "NS_Countries", "BS_Countries") to a shared
 * msp_config_generic_name (e.g. "Countries").
 *
 * Source of truth is a reviewable mapping file (generic name => [layer names]). Layer names that are
 * not in the file get a proposed name:
 *   1. PascalCase of layer_short ("Play Area" => "PlayArea"), or, if empty, the layer name without its
 *      region prefix (play area layers are always "PlayArea").
 *   2. The same layer name in several configs always gets the same generic name.
 *   3. If a proposed name is already used by a layer with a different layer_geotype, the geotype is
 *      appended ("Sediments_Raster").
 *   4. If two different layers of ONE config end up with the same generic name, the later ones get
 *      "_2", "_3", ... (generic names must be unique per config).
 * Proposals are reported so they can be reviewed and fixed in the mapping file.
 */
final class GenericNameRegistry
{
    /** @var array<string, string> layer name => generic name */
    private array $byLayer = [];

    /** @var array<string, string> generic name => layer_geotype of the first layer using it */
    private array $geoTypes = [];

    /** @var array<string, string> layer names that were not in the mapping file => proposed generic name */
    private array $proposed = [];

    /** @var string[] */
    private array $warnings = [];

    /**
     * @param array<string, string[]> $map generic name => layer names (the mapping file content)
     */
    public static function fromMap(array $map): self
    {
        $registry = new self();
        foreach ($map as $generic => $layerNames) {
            foreach ($layerNames as $layerName) {
                if (isset($registry->byLayer[$layerName]) && $registry->byLayer[$layerName] !== (string)$generic) {
                    throw new \InvalidArgumentException(sprintf(
                        'Layer "%s" is mapped to both "%s" and "%s" in the mapping file.',
                        $layerName,
                        $registry->byLayer[$layerName],
                        $generic
                    ));
                }
                $registry->byLayer[$layerName] = (string)$generic;
            }
        }
        return $registry;
    }

    /**
     * @param array<string, \stdClass> $configs config id => decoded config (root with "datamodel")
     */
    public function register(array $configs): void
    {
        foreach ($configs as $root) {
            foreach ($root->datamodel->meta ?? [] as $layer) {
                $name = $layer->layer_name;
                if (isset($this->byLayer[$name])) {
                    $this->geoTypes[$this->byLayer[$name]] ??= (string)($layer->layer_geotype ?? '');
                    continue;
                }
                $candidate = $this->candidate($layer);
                $geoType = (string)($layer->layer_geotype ?? '');
                if (isset($this->geoTypes[$candidate]) && $this->geoTypes[$candidate] !== $geoType) {
                    $candidate .= '_' . ConfigValues::pascalCase($geoType);
                }
                $this->geoTypes[$candidate] ??= $geoType;
                $this->byLayer[$name] = $candidate;
                $this->proposed[$name] = $candidate;
            }
        }
        $this->resolveCollisions($configs);
    }

    public function get(string $layerName): ?string
    {
        return $this->byLayer[$layerName] ?? null;
    }

    /**
     * Resolves a layer reference that may carry a "|<type>" suffix ("NS_Wind_Farms_Implemented|0").
     */
    public function resolveReference(string $reference): ?string
    {
        $parts = explode('|', $reference, 2);
        $generic = $this->byLayer[$parts[0]] ?? null;
        if ($generic === null) {
            return null;
        }
        return isset($parts[1]) ? $generic . '|' . $parts[1] : $generic;
    }

    /**
     * @return array<string, string[]> generic name => sorted layer names, ready to be written
     */
    public function toMap(): array
    {
        $map = [];
        foreach ($this->byLayer as $layerName => $generic) {
            $map[$generic][] = $layerName;
        }
        ksort($map, SORT_STRING | SORT_FLAG_CASE);
        foreach ($map as &$names) {
            sort($names, SORT_STRING);
        }
        return $map;
    }

    /**
     * @return array<string, string> layer name => generic name, only entries not found in the mapping file
     */
    public function proposed(): array
    {
        return $this->proposed;
    }

    /**
     * @return string[]
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    private function candidate(\stdClass $layer): string
    {
        $short = trim((string)($layer->layer_short ?? ''));
        if ($short !== '' && ($name = ConfigValues::pascalCase($short)) !== '') {
            return $name;
        }
        if (preg_match('/^_?play_?area/i', $layer->layer_name)) {
            return 'PlayArea'; // the example name in the design document
        }
        $withoutPrefix = preg_replace('/^[A-Za-z]{1,6}_/', '', $layer->layer_name) ?? $layer->layer_name;
        return ConfigValues::pascalCase($withoutPrefix) ?: $layer->layer_name;
    }

    /**
     * @param array<string, \stdClass> $configs
     */
    private function resolveCollisions(array $configs): void
    {
        foreach ($configs as $configId => $root) {
            $owners = []; // generic => layer name that keeps it in this config
            foreach ($root->datamodel->meta ?? [] as $layer) {
                $name = $layer->layer_name;
                $generic = $this->byLayer[$name];
                if (!isset($owners[$generic]) || $owners[$generic] === $name) {
                    $owners[$generic] = $name;
                    continue;
                }
                // Only rename layers we proposed ourselves, never ones coming from the mapping file.
                if (!isset($this->proposed[$name])) {
                    throw new \RuntimeException(sprintf(
                        'Config "%s": layers "%s" and "%s" both map to generic name "%s" in the mapping file.',
                        $configId,
                        $owners[$generic],
                        $name,
                        $generic
                    ));
                }
                $counter = 2;
                do {
                    $alternative = $generic . '_' . $counter++;
                } while (isset($owners[$alternative]) || isset($this->geoTypes[$alternative]));
                $this->warnings[] = sprintf(
                    'Generic name collision in "%s": "%s" and "%s" both proposed "%s"; "%s" renamed to "%s".',
                    $configId,
                    $owners[$generic],
                    $name,
                    $generic,
                    $name,
                    $alternative
                );
                $this->byLayer[$name] = $alternative;
                $this->proposed[$name] = $alternative;
                $this->geoTypes[$alternative] = (string)($layer->layer_geotype ?? '');
                $owners[$alternative] = $name;
            }
        }
    }
}
