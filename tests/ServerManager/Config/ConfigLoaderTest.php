<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\ConfigDirectory;
use App\Domain\Config\ConfigLoader;
use App\Domain\Config\ConfigParentException;
use App\Domain\Config\ConfigParents;
use App\Domain\Config\InvalidSessionConfigException;
use App\Domain\Config\Merge\RegionConfigMerger;
use App\Domain\Config\SessionConfigValidator;

/**
 * The ConfigLoader on a temporary config folder.
 */
class ConfigLoaderTest extends ConfigCommandTestCase
{
    private const string ID = 'North_Sea_basic/North_Sea_basic_1';

    private function loader(): ConfigLoader
    {
        // the folder parameter of the application ends with a slash
        return new ConfigLoader(
            $this->dir . '/',
            new SessionConfigValidator(self::projectDir() . '/src/Domain/SessionConfigJSONSchema.json')
        );
    }

    private function original(): string
    {
        return self::realConfigFiles()[self::ID];
    }

    private function writeHandMadeGeneric(string $json, string $name = 'generic'): void
    {
        new ConfigDirectory($this->dir)->write($this->dir . '/' . $name . '.json', ConfigFactory::json($json));
    }

    /**
     * The stripped version of the North Sea config, naming $parent as its parent, as JSON.
     */
    private function stripped(string $parent = 'generic'): string
    {
        [$split] = self::realSplit();
        $region = ConfigFactory::copy($split->regions[self::ID]);
        $region->metadata->parent = $parent;
        return ConfigDirectory::encode($region);
    }

    public function testACompleteConfigNeedsNoParentAndOnlyMovesToTheNewShape(): void
    {
        $merged = $this->loader()->merge($this->original());

        $this->assertSame([], self::comparator()->differences(
            RegionConfigMerger::toSimulationSettings(self::realConfigs()[self::ID]),
            $merged
        ));
    }

    public function testAStrippedConfigIsMergedWithItsParent(): void
    {
        $this->writeGeneric();

        $merged = $this->loader()->merge($this->stripped());

        $this->assertSame([], self::comparator()->differences(
            self::normalizer()->normalize(self::realConfigs()[self::ID]),
            $merged
        ));
        $this->assertFalse(isset($merged->metadata->parent), 'the final config has no parent');
    }

    public function testAStrippedConfigIsMergedThroughAChainOfParents(): void
    {
        $this->writeGeneric();
        $this->writeChildGeneric('public', 'generic');

        $merged = $this->loader()->merge($this->stripped('public'));

        $this->assertSame([], self::comparator()->differences(
            self::normalizer()->normalize(self::realConfigs()[self::ID]),
            $merged
        ));
    }

    public function testAStrippedConfigWithoutItsParentIsReported(): void
    {
        $this->expectException(ConfigParentException::class);
        $this->expectExceptionMessage('The parent "generic" of the config was not found: generic.json is needed.');
        $this->loader()->merge($this->stripped());
    }

    public function testTheMergedJsonHasTheShapeOfTheSchema(): void
    {
        $this->writeGeneric();

        $final = json_decode($this->loader()->mergedJson($this->original()));

        $this->assertTrue(isset($final->datamodel->simulation_settings->SEL));
        $this->assertFalse(isset($final->datamodel->SEL));
        $this->assertSame([], $this->loader()->errors($final));
    }

    public function testAConfigThatIsFinalAlreadyIsNotMergedWithItsParentAgain(): void
    {
        $this->writeHandMadeGeneric(
            '{"datamodel": {"meta": [], "simulation_settings": {"CEL": {"only_in_generic": 1}}}}'
        );
        $running = self::normalizer()->normalize(self::realConfigs()[self::ID]);
        $running->metadata->parent = 'generic';
        $json = ConfigDirectory::encode($running);

        $merged = $this->loader()->merge($json);
        $normalized = $this->loader()->normalize($json);

        $this->assertSame(1, $merged->datamodel->simulation_settings->CEL->only_in_generic);
        $this->assertFalse(isset($normalized->datamodel->simulation_settings->CEL->only_in_generic));
    }

    public function testNormalizingAnOldStyleConfigMovesItsSimulationSettings(): void
    {
        $normalized = $this->loader()->normalize($this->original());

        $this->assertFalse(isset($normalized->datamodel->SEL));
        $this->assertSameJson(
            self::realConfigs()[self::ID]->datamodel->SEL,
            $normalized->datamodel->simulation_settings->SEL
        );
    }

    public function testAParentIsReadAgainWhenItsFileChanges(): void
    {
        $stripped = '{"metadata": {"parent": "generic"}, "datamodel": {"meta": []}}';
        $this->writeHandMadeGeneric('{"datamodel": {"meta": [], "simulation_settings": {"CEL": {"x": 1}}}}');
        $loader = $this->loader();
        $this->assertSame(1, $loader->merge($stripped)->datamodel->simulation_settings->CEL->x);

        // a messenger worker lives long: a changed parent has to be noticed
        $this->writeHandMadeGeneric('{"datamodel": {"meta": [], "simulation_settings": {"CEL": {"x": 2}}}}');
        $this->assertSame(2, $loader->merge($stripped)->datamodel->simulation_settings->CEL->x);

        unlink($this->dir . '/generic.json');
        $this->expectException(ConfigParentException::class);
        $loader->merge($stripped);
    }

    public function testABrokenParentFileNamesTheFile(): void
    {
        file_put_contents($this->dir . '/generic.json', "{\n \"datamodel\": {\n  \"meta\": [] \n  \"x\": 1\n }\n}");

        try {
            $this->loader()->merge($this->stripped());
            $this->fail('the parent is not valid JSON');
        } catch (ConfigParentException $e) {
            $this->assertStringContainsString($this->dir . '/generic.json cannot be used', $e->getMessage());
        }
    }

    public function testASyntaxErrorIsReportedWithItsLine(): void
    {
        try {
            $this->loader()->decode("{\n \"a\": 1\n \"b\": 2}");
            $this->fail('the JSON is not valid');
        } catch (InvalidSessionConfigException $e) {
            $this->assertStringStartsWith("Invalid JSON, line 3, column 2: ',' or '}' is expected", $e->getErrors()[0]);
        }
    }

    public function testJsonThatIsNoObjectIsRefusedAndAByteOrderMarkIsFine(): void
    {
        $this->assertSame(1, $this->loader()->decode("\xEF\xBB\xBF{\"a\": 1}")->a);
        $this->expectException(InvalidSessionConfigException::class);
        $this->expectExceptionMessage('not an object');
        $this->loader()->decode('[1, 2]');
    }

    public function testTheMergedJsonOfAFileAndOfItsContentsAreTheSame(): void
    {
        $this->writeOriginals();
        $this->writeGeneric();

        $this->assertSameContents(
            $this->loader()->mergedJson($this->original()),
            $this->loader()->mergedJsonOfFile($this->dir . '/' . self::ID . '.json')
        );
    }

    public function testAMissingFileIsReported(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot read contents of the configuration file');
        $this->loader()->mergedJsonOfFile($this->dir . '/nope.json');
    }

    public function testASettingIsReadableTheOldAndTheNewWay(): void
    {
        $new = ConfigLoader::withBothShapes(['datamodel' => [
            'simulation_settings' => ['CEL' => ['a' => 1], 'SEL' => null, 'MEL' => ['rows' => 2]],
            'x' => 1,
        ]]);
        $old = ConfigLoader::withBothShapes(['datamodel' => ['CEL' => ['a' => 1], 'SEL' => ['s' => 1], 'MEL' => null]]);

        $this->assertSame(['a' => 1], $new['datamodel']['CEL']);
        $this->assertTrue(array_key_exists('SEL', $new['datamodel']) && $new['datamodel']['SEL'] === null);
        $this->assertSame(['rows' => 2], $new['datamodel']['MEL']);
        $this->assertSame(
            ['CEL' => ['a' => 1], 'SEL' => ['s' => 1], 'MEL' => null],
            $old['datamodel']['simulation_settings']
        );
        $this->assertSame($old, ConfigLoader::withBothShapes($old), 'a second time changes nothing');
        $this->assertSame(['x' => 1], ConfigLoader::withBothShapes(['x' => 1]));
    }

    public function testACompleteUploadIsStoredAsTheCompleteFinalConfigInTheNewShape(): void
    {
        $check = $this->loader()->checkUpload($this->original());

        $this->assertTrue($check->isValid(), implode("\n", $check->errors));
        $stored = ConfigFactory::json($check->contents);
        $this->assertTrue(isset($stored->datamodel->simulation_settings->SEL));
        $this->assertFalse(isset($stored->datamodel->SEL));
        $this->assertFalse(ConfigParents::needsParent($stored), 'it needs no parent file');
        $this->assertSame([], self::comparator()->differences(
            RegionConfigMerger::toSimulationSettings(self::realConfigs()[self::ID]),
            $stored
        ), 'a complete config is kept as it is, only moved to the new shape');
    }

    public function testAnUploadThatNamesItsParentIsStoredCompleteSoItNeedsNoParentAnymore(): void
    {
        $this->writeGeneric();
        $this->writeChildGeneric('public', 'generic');

        $check = $this->loader()->checkUpload($this->stripped('public'));

        $this->assertTrue($check->isValid(), implode("\n", $check->errors));
        $stored = ConfigFactory::json($check->contents);
        $this->assertFalse(isset($stored->metadata->parent));
        $this->assertFalse(ConfigParents::needsParent($stored));
        $this->assertSame([], self::comparator()->differences(
            self::normalizer()->normalize(self::realConfigs()[self::ID]),
            $stored
        ));
    }

    public function testAnUploadWithAParentThatThisServerDoesNotHaveIsRefusedWithTheFileThatIsNeeded(): void
    {
        $check = $this->loader()->checkUpload($this->stripped('restricted_base'));

        $this->assertFalse($check->isValid());
        $this->assertSame(
            ['The parent "restricted_base" of the uploaded config was not found: restricted_base.json is needed.'],
            $check->errors
        );
    }

    public function testADownloadThatIsEditedAndUploadedAgainKeepsTheEdit(): void
    {
        $this->writeGeneric();
        $download = ConfigFactory::json($this->loader()->mergedJson($this->original()));
        $download->datamodel->meta[0]->layer_tooltip = 'edited after the download';

        $check = $this->loader()->checkUpload(json_encode($download));

        $this->assertTrue($check->isValid(), implode("\n", $check->errors));
        $stored = ConfigFactory::json($check->contents);
        $this->assertSame('edited after the download', $stored->datamodel->meta[0]->layer_tooltip);
        $this->assertSame([], self::comparator()->differences($download, $stored));
    }

    public function testAnUploadWithASyntaxErrorIsRefusedWithTheLine(): void
    {
        $check = $this->loader()->checkUpload(
            "{\n  \"metadata\": {},\n  \"datamodel\": {\n    \"a\": 1\n    \"b\": 2\n  }\n}"
        );

        $this->assertFalse($check->isValid());
        $this->assertNull($check->contents);
        $this->assertStringStartsWith('Invalid JSON, line 5, column 5', $check->errors[0]);
    }

    public function testAnUploadThatDoesNotMatchTheSchemaIsRefusedWithTheProblems(): void
    {
        $config = ConfigFactory::copy(self::realConfigs()[self::ID]);
        unset($config->datamodel->edition_name);
        foreach ($config->datamodel->meta as $layer) {
            if ($layer->layer_geotype === 'raster') {
                unset($layer->layer_height);
                break;
            }
        }

        $check = $this->loader()->checkUpload(json_encode($config));

        $this->assertFalse($check->isValid());
        $errors = implode("\n", $check->errors);
        $this->assertStringContainsString('[datamodel.edition_name] The property edition_name is required', $errors);
        $this->assertStringContainsString('A raster layer needs a layer_height', $errors);
    }

    public function testAnUploadThatIsNoJsonObjectIsRefused(): void
    {
        $check = $this->loader()->checkUpload('[1, 2]');

        $this->assertFalse($check->isValid());
        $this->assertSame(['The JSON is valid, but it is not an object'], $check->errors);
    }
}
