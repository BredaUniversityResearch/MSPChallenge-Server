<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\ConfigDirectory;
use App\Domain\Config\ConfigParentException;
use App\Domain\Config\ConfigParents;
use App\Domain\Config\Split\ConfigValues;

class ConfigParentsTest extends ConfigCommandTestCase
{
    /**
     * @param array<string, \stdClass> $files name => generic config
     */
    private static function parents(array $files): ConfigParents
    {
        return new ConfigParents(static fn(string $name): ?\stdClass => $files[$name] ?? null);
    }

    /**
     * @param string[] $layers generic names of the layers
     */
    private static function generic(array $layers, ?string $parent = null, array $names = []): \stdClass
    {
        $generic = ConfigFactory::json('{"metadata": {"config_version": "2.0.0"}, "datamodel": {"meta": []}}');
        foreach ($layers as $name) {
            $generic->datamodel->meta[] = (object)['msp_config_generic_name' => $name, 'layer_category' => $name];
        }
        if ($parent !== null) {
            $generic->metadata->parent = $parent;
        }
        if ($names !== []) {
            $generic->layer_names = (object)$names;
        }
        return $generic;
    }

    /**
     * @return string[]
     */
    private static function layerNames(\stdClass $pool): array
    {
        return array_map(static fn($layer) => $layer->msp_config_generic_name, $pool->datamodel->meta);
    }

    public function testAConfigWithoutAParentHasAnEmptyPool(): void
    {
        $pool = self::parents([])->poolOf(ConfigFactory::config([ConfigFactory::layer('X_A', 'A')]));

        $this->assertSame([], $pool->datamodel->meta);
    }

    public function testAConfigThatRefersToGenericLayersNeedsAParent(): void
    {
        $config = ConfigFactory::config([ConfigFactory::layer('X_A', 'A')]);
        $config->datamodel->meta[0]->msp_config_generic_name = 'A';

        $this->assertTrue(ConfigParents::needsParent($config));
        $this->expectException(ConfigParentException::class);
        $this->expectExceptionMessage('but no metadata.parent');
        self::parents([])->poolOf($config, 'config "x"');
    }

    public function testTheParentIsReadFromTheMetadata(): void
    {
        $config = ConfigFactory::json('{"metadata": {"parent": "public"}}');

        $this->assertSame('public', ConfigParents::parentOf($config));
        $this->assertNull(ConfigParents::parentOf(ConfigFactory::json('{"metadata": {}}')));
        $this->assertNull(ConfigParents::parentOf(ConfigFactory::json('{}')));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidParentNames(): iterable
    {
        yield 'a path' => ['../generic'];
        yield 'a folder' => ['sub/generic'];
        yield 'with the extension' => ['generic.json'];
        yield 'a space' => ['my generic'];
    }

    /**
     * @dataProvider invalidParentNames
     */
    public function testTheNameOfAParentHasToBeAFileNameWithoutAnExtension(string $name): void
    {
        $config = ConfigFactory::json('{"metadata": {}}');
        $config->metadata->parent = $name;

        $this->expectException(ConfigParentException::class);
        $this->expectExceptionMessage('metadata.parent has to be the name of a file');
        ConfigParents::parentOf($config);
    }

    public function testThePoolOfAParentIsThatParent(): void
    {
        $pool = self::parents(['base' => self::generic(['A', 'B'])])->poolOfParent('base');

        $this->assertSame(['A', 'B'], self::layerNames($pool));
    }

    public function testAChainIsMergedFromTheRootDownAndTheChildAddsAndChangesLayers(): void
    {
        $base = self::generic(['A', 'B']);
        $public = self::generic(['B', 'C'], 'base');
        $public->datamodel->meta[0]->layer_category = 'changed by public';
        $restricted = self::generic(['D'], 'public');

        $parents = self::parents(['base' => $base, 'public' => $public, 'restricted' => $restricted]);
        $pool = $parents->poolOfParent('restricted');

        $this->assertSame(['A', 'B', 'C', 'D'], self::layerNames($pool));
        $this->assertSame('changed by public', $pool->datamodel->meta[1]->layer_category);
        $this->assertSame('A', $pool->datamodel->meta[0]->layer_category, 'what nobody changed stays');
        $this->assertSame(['A', 'B'], self::layerNames($parents->poolOfParent('base')), 'each level has its own pool');
        $this->assertSame(['A', 'B', 'C'], self::layerNames($parents->poolOfParent('public')));
    }

    public function testTheLayersOfASiblingAreNotInThePool(): void
    {
        $base = self::generic(['A']);
        $public = self::generic(['P'], 'base');
        $restricted = self::generic(['R'], 'base');
        $parents = self::parents(['base' => $base, 'public' => $public, 'restricted' => $restricted]);

        $this->assertSame(['A', 'P'], self::layerNames($parents->poolOfParent('public')));
        $this->assertSame(['A', 'R'], self::layerNames($parents->poolOfParent('restricted')));
    }

    public function testThePoolOfAConfigIsThePoolOfItsParent(): void
    {
        $config = ConfigFactory::json('{"metadata": {"parent": "public"}}');
        $parents = self::parents(['base' => self::generic(['A']), 'public' => self::generic(['B'], 'base')]);

        $this->assertSame(['A', 'B'], self::layerNames($parents->poolOf($config)));
    }

    public function testTheLayerNamesOfTheWholeChainAreKnown(): void
    {
        $base = self::generic(['Countries'], null, ['Countries' => ['A_Countries']]);
        $public = self::generic(['Ports'], 'base', ['Countries' => ['B_Countries'], 'Ports' => ['B_Ports']]);
        $parents = self::parents(['base' => $base, 'public' => $public]);

        $map = $parents->nameMapOfParent('public');

        $this->assertSame(['Countries' => ['A_Countries', 'B_Countries'], 'Ports' => ['B_Ports']], $map);
        $names = $parents->namesOfParent('public');
        $this->assertSame('Countries', $names->get('A_Countries'));
        $this->assertSame('Ports', $names->get('B_Ports'));
        $this->assertSame(
            ['Countries' => ['A_Countries']],
            $parents->nameMapOfParent('base'),
            'the base only knows its own'
        );
    }

    public function testTheLayerNamesOfAGenericConfigThatHasNoneAreEmpty(): void
    {
        $this->assertSame([], ConfigParents::nameMapOf(self::generic(['A'])));
        $withNames = self::generic(['A'], null, ['A' => ['x', 'y']]);
        $this->assertSame(['A' => ['x', 'y']], ConfigParents::nameMapOf($withNames));
    }

    public function testAMissingParentIsReportedWithTheFileThatIsNeeded(): void
    {
        $this->expectException(ConfigParentException::class);
        $this->expectExceptionMessage('The parent "zzz" of config "c" was not found: zzz.json is needed.');
        self::parents([])->poolOfParent('zzz', 'config "c"');
    }

    public function testAMissingParentHigherUpIsReportedAsTheParentOfItsChild(): void
    {
        $this->expectException(ConfigParentException::class);
        $this->expectExceptionMessage('The parent "base" of "public" was not found: base.json is needed.');
        self::parents(['public' => self::generic(['A'], 'base')])->poolOfParent('public', 'config "c"');
    }

    public function testParentsThatLoopAreReported(): void
    {
        $parents = self::parents(['a' => self::generic([], 'b'), 'b' => self::generic([], 'a')]);

        $this->expectException(ConfigParentException::class);
        $this->expectExceptionMessage('The parents of config "c" loop: a -> b -> a');
        $parents->poolOfParent('a', 'config "c"');
    }

    public function testAParentThatIsItsOwnParentIsALoop(): void
    {
        $this->expectException(ConfigParentException::class);
        $this->expectExceptionMessage('loop: a -> a');
        self::parents(['a' => self::generic([], 'a')])->poolOfParent('a', 'config "c"');
    }

    public function testTooManyLevelsAreRefused(): void
    {
        $files = [];
        for ($i = 0; $i <= ConfigParents::MAX_DEPTH; $i++) {
            $files['p' . $i] = self::generic([], $i < ConfigParents::MAX_DEPTH ? 'p' . ($i + 1) : null);
        }

        $this->expectException(ConfigParentException::class);
        $this->expectExceptionMessage('more than ' . ConfigParents::MAX_DEPTH . ' levels of parents');
        self::parents($files)->poolOfParent('p0', 'config "c"');
    }

    public function testOnlyAGenericConfigCanBeAParent(): void
    {
        $complete = ConfigFactory::config([ConfigFactory::layer('X_A', 'A')]); // layers have no generic name

        $this->expectException(ConfigParentException::class);
        $this->expectExceptionMessage('"base" cannot be a parent: layer 0 has no msp_config_generic_name');
        self::parents(['base' => $complete])->poolOfParent('base');
    }

    public function testParentsAreReadFromTheFilesOfAConfigFolder(): void
    {
        $directory = new ConfigDirectory($this->dir);
        $directory->write($directory->parentPath('base'), self::generic(['A']));
        $directory->write($directory->parentPath('public'), self::generic(['B'], 'base'));

        $pool = ConfigParents::fromDirectory($directory)->poolOfParent('public');

        $this->assertSame(['A', 'B'], self::layerNames($pool));
    }

    public function testAParentIsFoundByItsNameInAnyFolderAndNotOnlyNextToTheChildOrAboveIt(): void
    {
        $directory = new ConfigDirectory($this->dir);
        $directory->write($this->dir . '/NS/shared/base.json', self::generic(['A']));
        $directory->write($this->dir . '/SEA/other/deep/public.json', self::generic(['B'], 'base')); // another subtree

        $fingerprints = new \ArrayObject();
        $pool = ConfigParents::fromDirectory($directory, [], $fingerprints)->poolOfParent('public');

        $this->assertSame(['A', 'B'], self::layerNames($pool));
        $names = array_keys($fingerprints->getArrayCopy());
        sort($names); // they are read child first
        $this->assertSame(['base', 'public'], $names);
        $this->assertSame('NS/shared/base.json', $fingerprints['base']['path'], 'where it is, from the root');
        $this->assertSame('SEA/other/deep/public.json', $fingerprints['public']['path']);
        $this->assertSame(
            ConfigDirectory::fingerprint((string)file_get_contents($this->dir . '/NS/shared/base.json')),
            $fingerprints['base']['fingerprint']
        );
    }

    public function testTwoParentsWithTheSameNameAreAnErrorThatNamesBoth(): void
    {
        $directory = new ConfigDirectory($this->dir);
        $directory->write($this->dir . '/A/base.json', self::generic(['A']));
        $directory->write($this->dir . '/B/C/base.json', self::generic(['B']));

        try {
            ConfigParents::fromDirectory($directory)->poolOfParent('base', 'config "c"');
            $this->fail('which base.json is meant can not be told');
        } catch (ConfigParentException $e) {
            $this->assertStringContainsString('The parent "base" is ambiguous', $e->getMessage());
            $this->assertStringContainsString('A/base.json and B/C/base.json have that name', $e->getMessage());
        }
    }

    public function testAParentThatIsNowhereInTheTreeIsReportedWithTheFileThatIsNeeded(): void
    {
        $directory = new ConfigDirectory($this->dir);
        $directory->write($this->dir . '/A/other.json', self::generic(['A']));

        $this->expectException(ConfigParentException::class);
        $this->expectExceptionMessage('The parent "base" of config "c" was not found: base.json is needed.');
        ConfigParents::fromDirectory($directory)->poolOfParent('base', 'config "c"');
    }

    public function testAFileOfAConfigThatIsNamedLikeAParentIsNoParent(): void
    {
        $directory = new ConfigDirectory($this->dir);
        $directory->write(
            $this->dir . '/NS/base.json',
            ConfigFactory::config([ConfigFactory::layer('X_A', 'A')])
        ); // a config, not a generic config

        $this->expectException(ConfigParentException::class);
        $this->expectExceptionMessage('"base" cannot be a parent');
        ConfigParents::fromDirectory($directory)->poolOfParent('base');
    }

    public function testAGenericConfigIsToldByItsContentAndNotByItsPlace(): void
    {
        $this->assertTrue(ConfigParents::isGeneric(self::generic(['A'])));
        $this->assertTrue(ConfigParents::isGeneric(self::generic([])), 'a generic config can hold only sections');
        $this->assertFalse(ConfigParents::isGeneric(ConfigFactory::config([ConfigFactory::layer('X_A', 'A')])));
        $this->assertFalse(ConfigParents::isGeneric(ConfigFactory::json('{"some": "settings"}')));
    }

    public function testAParentFileThatIsNoValidJsonNamesTheFile(): void
    {
        file_put_contents($this->dir . '/base.json', '{ not json');

        try {
            ConfigParents::fromDirectory(new ConfigDirectory($this->dir))->poolOfParent('base', 'config "c"');
            $this->fail('the parent file is not valid JSON');
        } catch (ConfigParentException $e) {
            $this->assertStringContainsString($this->dir . '/base.json cannot be used', $e->getMessage());
        }
    }

    public function testTheRealConfigsGiveTheirOriginalsThroughAChainOfTwoGenericConfigs(): void
    {
        [$split] = self::realSplit();
        $layers = $split->generic->datamodel->meta;
        $half = intdiv(count($layers), 2);
        // the generic config in two: the base has the sections and the first layers, the second one the rest, and it
        // puts a layer of the base back to its value after the base changed it
        $base = ConfigFactory::copy($split->generic);
        unset($base->layer_names);
        $base->datamodel->meta = array_slice($layers, 0, $half);
        $base->datamodel->meta[0] = ConfigFactory::copy($layers[0]);
        $base->datamodel->meta[0]->layer_tooltip = 'WRONG, the second generic config has to correct it';
        $second = ConfigFactory::json('{"metadata": {"config_version": "2.0.0"}, "datamodel": {"meta": []}}');
        $second->metadata->parent = 'base';
        $correction = (object)[
            'msp_config_generic_name' => $layers[0]->msp_config_generic_name,
            'layer_tooltip' => $layers[0]->layer_tooltip ?? '',
        ];
        $second->datamodel->meta = array_merge([$correction], array_slice($layers, $half));
        $parents = self::parents(['base' => $base, 'second' => $second]);

        foreach (self::realConfigs() as $id => $original) {
            $region = ConfigFactory::copy($split->regions[$id]);
            $region->metadata->parent = 'second';
            $this->assertSame(
                [],
                self::differencesToOriginal($parents->poolOf($region, $id), $region, $original),
                $id
            );
        }
    }

    public function testTheFinalConfigDoesNotHaveAParent(): void
    {
        $config = ConfigFactory::config([ConfigFactory::layer('X_A', 'A')]);
        $config->metadata ??= new \stdClass();
        $config->metadata->parent = 'base';
        $parents = self::parents(['base' => self::generic([])]);

        $final = self::merger()->merge($parents->poolOf($config, 'c'), $config);

        $this->assertFalse(ConfigValues::has($final->metadata, 'parent'));
        $this->assertSame('base', $config->metadata->parent, 'the config itself is not changed');
    }
}
