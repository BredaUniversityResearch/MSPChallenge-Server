<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\ConfigLoader;
use App\Domain\Config\SessionConfigValidator;
use App\Domain\Config\UploadInspection;

/**
 * Looking at the files of an upload: the config, the parents that came with it, and what is still missing.
 */
class UploadInspectorTest extends ConfigCommandTestCase
{
    private const string ID = 'North_Sea_basic/North_Sea_basic_1';

    /**
     * @param array<string, string> $files
     */
    private function inspect(array $files): UploadInspection
    {
        // the server has the generic configs that are in $this->dir
        $loader = new ConfigLoader(
            $this->dir . '/',
            new SessionConfigValidator(self::projectDir() . '/src/Domain/SessionConfigJSONSchema.json')
        );
        return $loader->inspectUpload($files);
    }

    public function testAConfigWithoutParentsIsComplete(): void
    {
        $inspection = $this->inspect(['config.json' => self::realConfigFiles()[self::ID]]);

        $this->assertTrue($inspection->isComplete());
        $this->assertSame('config.json', $inspection->config);
        $this->assertSame([], $inspection->parents);
        $this->assertSame([], $inspection->missing);
        $this->assertSame('All the files are there.', $inspection->summary());
    }

    public function testAConfigAndItsParentTogetherAreComplete(): void
    {
        $inspection = $this->inspect([
            'child.json' => self::strippedJson(self::ID),
            'generic.json' => self::genericJson(),
        ]);

        $this->assertTrue($inspection->isComplete());
        $this->assertSame('child.json', $inspection->config);
        $this->assertSame(['generic.json'], $inspection->parents);
        $this->assertSame([], $inspection->serverParents);
    }

    public function testAMissingParentIsReportedWithTheFileThatNeedsIt(): void
    {
        $inspection = $this->inspect(['child.json' => self::strippedJson(self::ID)]);

        $this->assertTrue($inspection->isIncomplete());
        $this->assertSame([['file' => 'generic.json', 'neededBy' => 'child.json']], $inspection->missing);
        $this->assertFalse($inspection->missingConfig);
        $this->assertSame('Missing: generic.json, the parent of child.json.', $inspection->summary());
    }

    public function testTheServerSuppliesTheParentsThatAreNotUploaded(): void
    {
        $this->writeGeneric();

        $inspection = $this->inspect(['child.json' => self::strippedJson(self::ID)]);

        $this->assertTrue($inspection->isComplete());
        $this->assertSame(['generic'], $inspection->serverParents);
        $this->assertSame([], $inspection->parents);
    }

    public function testAChainIsFollowedAndTheFirstMissingParentIsReported(): void
    {
        $inspection = $this->inspect([
            'child.json' => self::strippedJson(self::ID, 'public'),
            'public.json' => self::emptyChildGenericJson('generic'),
        ]);

        $this->assertTrue($inspection->isIncomplete());
        $this->assertSame(['public.json'], $inspection->parents);
        $this->assertSame([['file' => 'generic.json', 'neededBy' => 'public.json']], $inspection->missing);
    }

    public function testAChainThatIsPartlyOnTheServerIsComplete(): void
    {
        $this->writeGeneric();

        $inspection = $this->inspect([
            'child.json' => self::strippedJson(self::ID, 'public'),
            'public.json' => self::emptyChildGenericJson('generic'),
        ]);

        $this->assertTrue($inspection->isComplete());
        $this->assertSame(['public.json'], $inspection->parents);
        $this->assertSame(['generic'], $inspection->serverParents);
    }

    public function testOnlyGenericConfigsMeansTheConfigItselfIsMissing(): void
    {
        $inspection = $this->inspect(['generic.json' => self::genericJson()]);

        $this->assertTrue($inspection->isIncomplete());
        $this->assertTrue($inspection->missingConfig);
        $this->assertNull($inspection->config);
        $this->assertSame(['generic.json'], $inspection->unused);
        $this->assertStringContainsString('The configuration itself is missing', $inspection->summary());
    }

    public function testAGenericConfigWithoutLayersIsStillAGenericConfig(): void
    {
        $sectionsOnly = '{"metadata": {"config_version": "2.0.0"}, "datamodel": {"meta": [], "dependencies": {}}}';

        $inspection = $this->inspect([
            'child.json' => self::smallConfigJson('generic'),
            'generic.json' => $sectionsOnly,
        ]);

        $this->assertTrue($inspection->isComplete(), $inspection->summary());
        $this->assertSame(['generic.json'], $inspection->parents);
    }

    public function testTwoConfigurationsAreRefused(): void
    {
        $inspection = $this->inspect([
            'a.json' => self::smallConfigJson(null),
            'b.json' => self::smallConfigJson(null),
        ]);

        $this->assertTrue($inspection->isInvalid());
        $this->assertSame(
            ['Upload one configuration at a time, together with its parents. '
                . 'These files are all configurations: a.json, b.json.'],
            $inspection->errors
        );
    }

    public function testAGenericConfigThatIsNotNeededIsReportedButDoesNotMatter(): void
    {
        $inspection = $this->inspect([
            'config.json' => self::smallConfigJson(null),
            'spare.json' => self::emptyChildGenericJson('generic'),
        ]);

        $this->assertTrue($inspection->isComplete());
        $this->assertSame(['spare.json'], $inspection->unused);
    }

    public function testParentsThatLoopAreInvalid(): void
    {
        $inspection = $this->inspect([
            'c.json' => self::smallConfigJson('a'),
            'a.json' => self::emptyChildGenericJson('b'),
            'b.json' => self::emptyChildGenericJson('a'),
        ]);

        $this->assertTrue($inspection->isInvalid());
        $this->assertSame(['The parents of c.json loop: a -> b -> a'], $inspection->errors);
    }

    public function testAChainThatIsTooLongIsInvalid(): void
    {
        $files = ['c.json' => self::smallConfigJson('p0')];
        for ($i = 0; $i <= 9; $i++) {
            $files['p' . $i . '.json'] = self::emptyChildGenericJson('p' . ($i + 1));
        }

        $inspection = $this->inspect($files);

        $this->assertTrue($inspection->isInvalid());
        $this->assertSame(['c.json has more than 8 levels of parents.'], $inspection->errors);
    }

    public function testAFileThatIsNoValidJsonIsInvalidWithItsLine(): void
    {
        $inspection = $this->inspect(['bad.json' => "{\n \"a\": 1\n \"b\": 2}"]);

        $this->assertTrue($inspection->isInvalid());
        $this->assertStringStartsWith('bad.json: Invalid JSON, line 3, column 2', $inspection->errors[0]);
    }

    public function testAStrippedConfigThatDoesNotNameItsParentIsInvalid(): void
    {
        $stripped = json_decode(self::strippedJson(self::ID));
        unset($stripped->metadata->parent);

        $inspection = $this->inspect(['child.json' => json_encode($stripped)]);

        $this->assertTrue($inspection->isInvalid());
        $this->assertStringStartsWith('child.json has layers that refer to a generic layer', $inspection->errors[0]);
    }

    public function testAParentIsFoundByTheNameOfItsFile(): void
    {
        $config = self::smallConfigJson('my_parent');

        $exact = $this->inspect(['c.json' => $config, 'my_parent.json' => self::emptyChildGenericJson('x')]);
        $capitals = $this->inspect(['c.json' => $config, 'my_parent.JSON' => self::emptyChildGenericJson('x')]);
        $otherExtension = $this->inspect(['c.json' => $config, 'my_parent.txt' => self::emptyChildGenericJson('x')]);
        $otherName = $this->inspect(['c.json' => $config, 'My_Parent.json' => self::emptyChildGenericJson('x')]);

        $this->assertSame(['my_parent.json'], $exact->parents);
        $this->assertSame(['my_parent.JSON'], $capitals->parents, 'the extension is not case sensitive');
        $this->assertSame(
            [['file' => 'my_parent.json', 'neededBy' => 'c.json']],
            $otherExtension->missing,
            'only a .json file can be a parent'
        );
        $this->assertSame(
            [['file' => 'my_parent.json', 'neededBy' => 'c.json']],
            $otherName->missing,
            'the name is case sensitive'
        );
    }
}
