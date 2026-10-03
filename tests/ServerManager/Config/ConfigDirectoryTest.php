<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\ConfigDirectory;
use Symfony\Component\Filesystem\Filesystem;

class ConfigDirectoryTest extends ConfigCommandTestCase
{
    private function put(string $relativePath, string $contents = '{}'): string
    {
        new Filesystem()->dumpFile($this->dir . '/' . $relativePath, $contents);
        return $this->dir . '/' . $relativePath;
    }

    public function testPathsAreShownRelativeWhenInsideTheBaseAndInFullOtherwise(): void
    {
        // A base with another root than the paths (like drive D: against drive C: on Windows): Path::makeRelative()
        // throws "cannot be made relative ... because they have different roots" for these. The "vfs://" stream
        // wrapper style gives such a base on every OS.
        $base = 'vfs://project';
        $elsewhere = $this->dir . '/generic.json';

        $this->assertSame(
            'ServerManager/configfiles/generic.json',
            ConfigDirectory::displayPath($base . '/ServerManager/configfiles/generic.json', $base)
        );
        $this->assertSame($elsewhere, ConfigDirectory::displayPath($elsewhere, $base), 'another root: in full');
        // the same root but not inside the base: also in full, never "../"
        $this->assertSame(
            $this->dir . '_other/file.json',
            ConfigDirectory::displayPath($this->dir . '_other/file.json', $this->dir)
        );
        $this->assertSame('a/b.json', ConfigDirectory::displayPath($this->dir . '/a/b.json', $this->dir));
    }

    public function testConfigsAreTheJsonFilesOneFolderDeepWithoutRegionFiles(): void
    {
        $this->put('b/b.json');
        $this->put('a/a.json');
        $this->put('a/a.region.json');
        $this->put('c/deeper/x.json');
        $this->put('generic.json');
        $this->put('a/notes.txt');

        $files = new ConfigDirectory($this->dir)->configFiles();

        $this->assertSame(['a/a', 'b/b'], array_keys($files));
        $this->assertSame(realpath($this->dir . '/a/a.json'), $files['a/a']);
    }

    public function testPatternSelectsFiles(): void
    {
        $this->put('a/a_basic_1.json');
        $this->put('a/a_other.json');

        $this->assertSame(['a/a_basic_1'], array_keys(new ConfigDirectory($this->dir)->configFiles('*_basic_1.json')));
    }

    public function testReadingIgnoresABomAndRequiresAJsonObject(): void
    {
        $directory = new ConfigDirectory($this->dir);

        $this->assertSame(1, $directory->read($this->put('bom.json', "\xEF\xBB\xBF{\"a\": 1}"))->a);
        try {
            $directory->read($this->put('list.json', '[1]'));
            $this->fail('a JSON list is not a config');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('does not contain a JSON object', $e->getMessage());
        }
        $this->expectException(\JsonException::class);
        $directory->read($this->put('broken.json', '{ nope'));
    }

    public function testFilesAreResolvedToIdsInsideAndOutsideTheDirectory(): void
    {
        $inside = $this->put('folder/name.json');
        $outside = $this->put('../' . basename($this->dir) . '_other/custom.json');
        $directory = new ConfigDirectory($this->dir);

        $files = $directory->resolveFiles([$inside, $outside], '/anywhere');

        $this->assertSame(['folder/name', 'custom'], array_keys($files));
        $this->assertSame(['folder/name' => $inside], array_intersect($files, [$inside]));
        new Filesystem()->remove($this->dir . '_other');
    }

    public function testRelativeFilesAreResolvedFromTheGivenBaseAndMissingFilesAreReported(): void
    {
        $this->put('folder/name.json');
        $directory = new ConfigDirectory($this->dir);

        $this->assertSame(['folder/name'], array_keys($directory->resolveFiles(['folder/name.json'], $this->dir)));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File not found: nope.json');
        $directory->resolveFiles(['nope.json'], $this->dir);
    }

    public function testWithoutArgumentsAllConfigsAreResolved(): void
    {
        $this->put('a/a.json');

        $this->assertSame(['a/a'], array_keys(new ConfigDirectory($this->dir)->resolveFiles([], '/anywhere')));
    }

    public function testAMissingGenericConfigIsReported(): void
    {
        $directory = new ConfigDirectory($this->dir);

        $this->assertFalse($directory->hasGeneric());
        $this->assertFalse($directory->hasGeneric('public'));
        try {
            $directory->loadGeneric('public');
            $this->fail('public.json is missing');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('public.json', $e->getMessage());
            $this->assertStringContainsString('app:config:split --apply', $e->getMessage());
        }
    }

    public function testAGenericConfigIsAFileInTheRootOfTheFolderNamedAfterIt(): void
    {
        $this->put('public.json', '{"datamodel": {"meta": []}}');
        $directory = new ConfigDirectory($this->dir);

        $this->assertTrue($directory->hasGeneric('public'));
        $this->assertSame($this->dir . '/public.json', $directory->parentPath('public'));
        $this->assertSame($this->dir . '/generic.json', $directory->genericPath());
        $this->assertNotNull($directory->loadGeneric('public')->datamodel);
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function parentNames(): iterable
    {
        yield 'a plain name' => ['generic', true];
        yield 'with digits, _ and -' => ['public_2-base', true];
        yield 'with a path' => ['../generic', false];
        yield 'with a folder' => ['a/generic', false];
        yield 'with the extension' => ['generic.json', false];
        yield 'with a space' => ['my generic', false];
        yield 'empty' => ['', false];
    }

    /**
     * @dataProvider parentNames
     */
    public function testOnlyFileNamesWithoutTheExtensionAreValidParentNames(string $name, bool $valid): void
    {
        $this->assertSame($valid, ConfigDirectory::isValidParentName($name));
    }

    public function testEncodedConfigsEndWithANewlineAndKeepFloats(): void
    {
        $json = ConfigDirectory::encode(ConfigFactory::json('{"a": 1.0, "b": "é/x"}'));

        $this->assertStringEndsWith("}\n", $json);
        $this->assertStringContainsString('1.0', $json);
        $this->assertStringContainsString('é/x', $json);
    }

    public function testEncodedConfigsAreIndentedWithTwoSpacesLikeTheOriginals(): void
    {
        $document = ConfigFactory::json('{"a": {"b": [1, {"c": "  two leading spaces\\nand a second line"}]}}');

        $json = ConfigDirectory::encode($document);

        $this->assertSame(
            "{\n  \"a\": {\n    \"b\": [\n      1,\n      {\n        \"c\": \"  two leading spaces\\nand a second line\"\n      }\n    ]\n  }\n}\n",
            $json
        );
        $this->assertSameJson($document, json_decode($json), 'the values themselves are untouched');
    }

    public function testEncodingKeepsTheSizeOfTheOriginals(): void
    {
        foreach (self::realConfigFiles() as $id => $contents) {
            $reencoded = ConfigDirectory::encode(json_decode($contents));
            // (a few lines of an original are indented with tabs, so it is not exactly the same size)
            $this->assertLessThanOrEqual(strlen($contents) * 1.01, strlen($reencoded), "$id would grow with 4 spaces");
        }
    }

    public function testAFileIsReplacedWhenTheWrittenVersionPassesTheVerification(): void
    {
        $path = $this->put('a/a.json', '{"old": true}');
        $seen = null;

        $problems = new ConfigDirectory($this->dir)->replaceVerified(
            $path,
            ConfigFactory::json('{"new": true}'),
            function (\stdClass $written) use (&$seen): array {
                $seen = $written;
                return [];
            }
        );

        $this->assertSame([], $problems);
        $this->assertTrue($seen->new, 'the verification gets the file as it was written');
        $this->assertSame(true, $this->decode($path)->new);
        $this->assertNoTemporaryFiles();
    }

    public function testAStagedFileIsOnlyReplacedWhenItIsCommitted(): void
    {
        $path = $this->put('a/a.json', '{"old": true}');
        $directory = new ConfigDirectory($this->dir);

        [$problems, $temporary] = $directory->stage($path, ConfigFactory::json('{"new": true}'), static fn() => []);

        $this->assertSame([], $problems);
        $this->assertSame('{"old": true}', file_get_contents($path));
        $this->assertSame(true, $this->decode($temporary)->new);
        $directory->commit($temporary, $path);
        $this->assertSame(true, $this->decode($path)->new);
        $this->assertNoTemporaryFiles();
    }

    public function testAStagedFileThatDoesNotPassIsRemovedAtOnce(): void
    {
        $path = $this->put('a/a.json', '{"old": true}');

        [$problems, $temporary] = new ConfigDirectory($this->dir)->stage(
            $path,
            ConfigFactory::json('{"new": true}'),
            static fn() => ['wrong']
        );

        $this->assertSame(['wrong'], $problems);
        $this->assertNull($temporary);
        $this->assertNoTemporaryFiles();
        $this->assertSame('{"old": true}', file_get_contents($path));
    }

    public function testStagedFilesThatWillNotBeCommittedCanBeDiscarded(): void
    {
        $directory = new ConfigDirectory($this->dir);
        [, $first] = $directory->stage($this->dir . '/a.json', ConfigFactory::json('{}'), static fn() => []);
        [, $second] = $directory->stage($this->dir . '/b.json', ConfigFactory::json('{}'), static fn() => []);

        $directory->discard($first, $second);

        $this->assertNoTemporaryFiles();
        $this->assertFileDoesNotExist($this->dir . '/a.json');
    }

    public function testAFileIsLeftAloneWhenTheVerificationFinds(): void
    {
        $path = $this->put('a/a.json', '{"old": true}');

        $problems = new ConfigDirectory($this->dir)->replaceVerified(
            $path,
            ConfigFactory::json('{"new": true}'),
            static fn(\stdClass $written): array => ['it is wrong']
        );

        $this->assertSame(['it is wrong'], $problems);
        $this->assertSame('{"old": true}', file_get_contents($path));
        $this->assertNoTemporaryFiles();
    }

    public function testAFileIsLeftAloneWhenTheVerificationThrows(): void
    {
        $path = $this->put('a/a.json', '{"old": true}');

        $problems = new ConfigDirectory($this->dir)->replaceVerified(
            $path,
            ConfigFactory::json('{"new": true}'),
            static fn(\stdClass $written) => throw new \LogicException('boom')
        );

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('boom', $problems[0]);
        $this->assertSame('{"old": true}', file_get_contents($path));
        $this->assertNoTemporaryFiles();
    }
}
