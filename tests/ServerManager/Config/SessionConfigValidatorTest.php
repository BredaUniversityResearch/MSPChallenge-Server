<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\InvalidSessionConfigException;
use App\Domain\Config\Merge\RegionConfigMerger;
use App\Domain\Config\SessionConfigValidator;
use App\Domain\Config\Split\ConfigValues;

/**
 * The schema in src/Domain/SessionConfigJSONSchema.json, and the rules the validator adds to it. The schema describes
 * the final config: CEL, SEL and MEL in datamodel.simulation_settings.
 */
class SessionConfigValidatorTest extends ConfigTestCase
{
    private static ?\stdClass $final = null;

    private static function validator(): SessionConfigValidator
    {
        return new SessionConfigValidator(self::projectDir() . '/src/Domain/SessionConfigJSONSchema.json');
    }

    /**
     * A copy of the final config of the North Sea config.
     */
    private static function finalConfig(): \stdClass
    {
        [$split] = self::realSplit();
        self::$final ??= self::merger()->merge(
            $split->generic,
            self::realConfigs()['North_Sea_basic/North_Sea_basic_1']
        );
        return ConfigFactory::copy(self::$final);
    }

    /**
     * @return \stdClass the first raster layer
     */
    private static function rasterLayer(\stdClass $config): \stdClass
    {
        foreach ($config->datamodel->meta as $layer) {
            if ($layer->layer_geotype === 'raster') {
                return $layer;
            }
        }
        throw new \LogicException('the config has no raster layer');
    }

    public function testTheFinalConfigOfEveryOriginalConfigIsValid(): void
    {
        [$split] = self::realSplit();

        foreach (self::realConfigs() as $id => $original) {
            $final = self::merger()->merge($split->generic, $original);
            $this->assertSame([], self::validator()->errors($final), $id);
        }
    }

    public function testAnOldStyleConfigIsValidOnceItIsNormalizedButNotBefore(): void
    {
        foreach (self::realConfigs() as $id => $original) {
            $this->assertSame(
                [],
                self::validator()->errors(RegionConfigMerger::toSimulationSettings($original)),
                "$id normalized"
            );
        }
        $errors = self::validator()->errors(array_values(self::realConfigs())[0]);
        $this->assertContains('[datamodel.simulation_settings] The property simulation_settings is required', $errors);
    }

    public function testAStrippedConfigIsValidAfterItIsMerged(): void
    {
        [$split] = self::realSplit();

        foreach ($split->regions as $id => $region) {
            $this->assertSame([], self::validator()->errors(self::merger()->merge($split->generic, $region)), $id);
        }
    }

    public function testSelAndMelAreNullWhenARegionHasNoShippingOrEcosystemSimulation(): void
    {
        $config = self::finalConfig();
        $config->datamodel->simulation_settings->SEL = null;
        $config->datamodel->simulation_settings->MEL = null;

        $this->assertSame([], self::validator()->errors($config));
    }

    public function testCelCannotBeNull(): void
    {
        $config = self::finalConfig();
        $config->datamodel->simulation_settings->CEL = null;

        $this->assertStringContainsString(
            '[datamodel.simulation_settings.CEL]',
            implode("\n", self::validator()->errors($config))
        );
    }

    public function testSimulationSettingsIsRequired(): void
    {
        $config = self::finalConfig();
        unset($config->datamodel->simulation_settings);

        $this->assertContains(
            '[datamodel.simulation_settings] The property simulation_settings is required',
            self::validator()->errors($config)
        );
    }

    public function testTheFieldsOfTheDesignDocumentAreNotRequired(): void
    {
        $config = self::finalConfig();
        foreach ($config->datamodel->meta as $layer) {
            unset($layer->layer_raster_filter_mode, $layer->layer_information);
            if ($layer->layer_geotype !== 'raster') {
                unset($layer->layer_width, $layer->layer_height);
            }
        }
        foreach ($config->datamodel->simulation_settings->MEL->ecologyCategories as $category) {
            unset($category->valueDefinitions);
        }

        $this->assertSame([], self::validator()->errors($config));
    }

    public function testARasterLayerNeedsItsHeightAndWidth(): void
    {
        $config = self::finalConfig();
        $layer = self::rasterLayer($config);
        unset($layer->layer_height, $layer->layer_width);

        $errors = self::validator()->errors($config);

        $index = array_search($layer, $config->datamodel->meta, true);
        foreach (['layer_height', 'layer_width'] as $key) {
            $this->assertContains(
                "[datamodel.meta[$index].$key] A raster layer needs a $key (the raster is downloaded with it)",
                $errors
            );
        }
    }

    public function testOtherRequiredPropertiesAndTypesAreStillChecked(): void
    {
        $config = self::finalConfig();
        unset($config->datamodel->meta[0]->layer_name, $config->datamodel->edition_name);
        $config->datamodel->start = 'soon';

        $errors = implode("\n", self::validator()->errors($config));

        $this->assertStringContainsString(
            '[datamodel.meta[0].layer_name] The property layer_name is required',
            $errors
        );
        $this->assertStringContainsString(
            '[datamodel.edition_name] The property edition_name is required',
            $errors
        );
        $this->assertStringContainsString('[datamodel.start]', $errors);
    }

    public function testValidateThrowsWithAllTheErrors(): void
    {
        $config = self::finalConfig();
        unset($config->datamodel->edition_name, $config->datamodel->region);

        try {
            self::validator()->validate($config);
            $this->fail('the config is invalid');
        } catch (InvalidSessionConfigException $e) {
            $this->assertCount(2, $e->getErrors());
            $this->assertStringContainsString('edition_name', $e->getMessage());
            $this->assertStringContainsString('region', $e->getMessage());
            $this->assertSame(
                $e->getErrors()[0] . '; ' . $e->getErrors()[1],
                $e->summary(),
                'two errors fit in the short form'
            );
        }
    }

    public function testValidateAcceptsAValidConfig(): void
    {
        self::validator()->validate(self::finalConfig());

        $this->addToAssertionCount(1);
    }

    public function testTheNumberOfErrorsIsLimited(): void
    {
        $config = self::finalConfig();
        foreach ($config->datamodel->meta as $layer) {
            unset($layer->layer_name);
        }

        $errors = self::validator()->errors($config, 5);

        $this->assertCount(6, $errors);
        $this->assertSame('... and more (only the first 5 are shown)', $errors[5]);
    }

    public function testTheSummaryOfALongListOfErrorsStaysShort(): void
    {
        $exception = new InvalidSessionConfigException(['a', 'b', 'c', 'd', 'e']);

        $this->assertSame('a; b; c (and 2 more)', $exception->summary());
        $this->assertSame('a; b; c; d; e', $exception->getMessage());
    }

    public function testTheShapeOfTheOriginalConfigIsNotTheOneOfTheSchemaAnyMore(): void
    {
        $config = self::finalConfig();
        $this->assertFalse(ConfigValues::has($config->datamodel, 'SEL'), 'the final config has no top-level SEL');
        $this->assertTrue(ConfigValues::has($config->datamodel, 'simulation_settings'));
    }
}
