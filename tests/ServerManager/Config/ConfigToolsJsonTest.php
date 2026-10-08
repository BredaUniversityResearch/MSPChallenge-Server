<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\ConfigDirectory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * What the config commands tell a program (--format=json): one JSON document on stdout and nothing else, the same
 * exit codes as in text, errors and warnings with codes, and the data of the result. See docs/config-tools-cli.md.
 *
 * $this->dir is the config root. Most tests start from the six original configs that are split already.
 */
class ConfigToolsJsonTest extends ConfigCommandTestCase
{
    private const string NORTH_SEA = 'North_Sea_basic/North_Sea_basic_1';

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed> the document that the command printed (and nothing else may be printed)
     */
    private function command(string $command, array $input = [], ?int $expectedExit = null): array
    {
        $tester = $this->execute($command, $this->asJson($command, $input));
        return $this->document($tester, $command, $expectedExit);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function asJson(string $command, array $input): array
    {
        $input += ['--format' => 'json'];
        if (in_array($command, ['app:config:split', 'app:config:strip'], true)) {
            $input += ['--skip-validation' => true]; // the originals are valid, and validating takes time
        }
        return $input;
    }

    /**
     * @return array<string, mixed>
     */
    private function document(CommandTester $tester, string $command, ?int $expectedExit): array
    {
        $document = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR); // all of stdout is JSON
        $this->assertSame(1, $document['schema']);
        $this->assertSame($command, $document['command']);
        $this->assertSame($tester->getStatusCode(), $document['exitCode'], 'the exit code is in the document too');
        $this->assertSame($document['exitCode'] === 0, $document['success']);
        $this->assertIsArray($document['errors']);
        $this->assertIsArray($document['warnings']);
        $this->assertIsArray($document['data']);
        if ($expectedExit !== null) {
            $this->assertSame($expectedExit, $tester->getStatusCode(), $tester->getDisplay());
        }
        return $document;
    }

    private function splitTheOriginals(): void
    {
        $this->writeOriginals();
        $tester = $this->execute('app:config:split', ['--apply' => true, '--skip-validation' => true]);
        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
    }

    /**
     * @param array<string, mixed> $document
     * @return list<string>
     */
    private static function errorCodes(array $document): array
    {
        return array_map(static fn(array $error) => $error['code'], $document['errors']);
    }

    public function testAnUnknownFormatIsAMistakeInHowTheCommandIsUsed(): void
    {
        $this->splitTheOriginals();

        $tester = $this->execute('app:config:list', ['--format' => 'xml']);

        $this->assertSame(2, $tester->getStatusCode());
        $this->assertStringContainsString('--format is text or json, not "xml"', self::text($tester));
    }

    public function testNothingButTheDocumentIsPrintedByAnyCommand(): void
    {
        $this->splitTheOriginals();
        $file = $this->dir . '/' . self::NORTH_SEA . '.json';

        foreach ([
            ['app:config:list', []],
            ['app:config:validate', []],
            ['app:config:merge', ['file' => $file]],
            ['app:config:verify', ['--original-dir' => $this->originalsDirectory()]],
            ['app:config:strip', []],
            ['app:config:split', []],
            ['app:config:split', ['--apply' => true, '--force' => true]],
        ] as [$command, $input]) {
            $tester = $this->execute($command, $this->asJson($command, $input));
            $document = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame($command, $document['command'], 'only JSON on stdout: ' . $command);
        }
    }

    private function originalsDirectory(): string
    {
        $originals = $this->temporaryDirectory();
        $this->writeOriginals($originals);
        return $originals;
    }

    // -------------------------------------------------------------------------------------------------- merge

    public function testMergeGivesTheFinalConfigAndTheParentsItWasMergedWith(): void
    {
        $this->splitTheOriginals();

        $input = ['file' => $this->dir . '/' . self::NORTH_SEA . '.json', '--format' => 'json'];
        $tester = $this->execute('app:config:merge', $input);
        $document = $this->document($tester, 'app:config:merge', 0);

        $data = $document['data'];
        $this->assertSame(self::NORTH_SEA, $data['file']);
        $this->assertSame(['generic'], array_column($data['parents'], 'name'));
        $this->assertSame('generic.json', $data['parents'][0]['path']);
        $this->assertSame(32, strlen($data['parents'][0]['fingerprint']));
        $this->assertArrayNotHasKey('parent', $data['config']['metadata'], 'a final config has no parent');
        // read as objects, like a program does (as arrays PHP turns {"0": ...} into a list)
        $config = json_decode($tester->getDisplay())->data->config;
        $this->assertSame([], self::comparator()->differences(
            self::normalizer()->normalize(self::realConfigs()[self::NORTH_SEA]),
            $config
        ), 'the config in the document is the original one');
    }

    public function testMergeToAFileSaysWhereItWroteAndLeavesTheConfigOutOfTheDocument(): void
    {
        $this->splitTheOriginals();
        $target = $this->temporaryDirectory() . '/merged.json';

        $document = $this->command(
            'app:config:merge',
            ['file' => $this->dir . '/' . self::NORTH_SEA . '.json', '--output' => $target],
            0
        );

        $this->assertSame($target, $document['data']['output']);
        $this->assertArrayNotHasKey('config', $document['data']);
        $this->assertFileExists($target);
    }

    public function testMergeToldWhichParentIsMissingAndWhoNeedsIt(): void
    {
        $this->splitTheOriginals();
        unlink($this->dir . '/generic.json');

        $document = $this->command('app:config:merge', ['file' => $this->dir . '/' . self::NORTH_SEA . '.json'], 1);

        $this->assertFalse($document['success']);
        $this->assertSame(['parent_missing'], self::errorCodes($document));
        $error = $document['errors'][0];
        $this->assertSame('generic', $error['details']['parent']);
        $this->assertSame('generic.json', $error['details']['file']);
        $this->assertSame(self::NORTH_SEA, $error['details']['neededBy']);
        $this->assertStringContainsString('generic.json is needed', $error['message']);
    }

    public function testMergeToldWhichFileHasTheNameOfAParentAsWell(): void
    {
        $this->splitTheOriginals();
        new Filesystem()->copy($this->dir . '/generic.json', $this->dir . '/NS/generic.json');

        $document = $this->command('app:config:merge', ['file' => $this->dir . '/' . self::NORTH_SEA . '.json'], 1);

        $this->assertSame(['parent_ambiguous'], self::errorCodes($document));
        $this->assertSame(['NS/generic.json', 'generic.json'], $document['errors'][0]['details']['paths']);
    }

    public function testMergeToldThatAFileIsNoValidJsonAndWhere(): void
    {
        $broken = $this->dir . '/broken.json';
        file_put_contents($broken, "{\n \"a\": 1\n \"b\": 2}");

        $document = $this->command('app:config:merge', ['file' => $broken], 1);

        $this->assertContains($document['errors'][0]['code'], ['invalid_json']);
    }

    public function testMergeToldThatAFileDoesNotExist(): void
    {
        $document = $this->command('app:config:merge', ['file' => $this->dir . '/nope.json'], 1);

        $this->assertSame(['file_not_found'], self::errorCodes($document));
    }

    public function testInTextModeTheConfigIsOnStdoutAndTheMessagesAreOnStderr(): void
    {
        $this->splitTheOriginals();
        unlink($this->dir . '/generic.json');
        $tester = new CommandTester(new Application(self::$kernel)->find('app:config:merge'));

        $tester->execute(
            ['file' => $this->dir . '/' . self::NORTH_SEA . '.json', '--dir' => $this->dir],
            ['capture_stderr_separately' => true, 'decorated' => false]
        );

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertSame('', $tester->getDisplay(), 'nothing on stdout, so that a pipe stays clean');
        $this->assertStringContainsString('generic.json is needed', $tester->getErrorOutput());
    }

    // ------------------------------------------------------------------------------------------------ validate

    public function testValidateSaysThatEveryConfigIsValid(): void
    {
        $this->splitTheOriginals();

        $document = $this->command('app:config:validate', [], 0);

        $this->assertSame(6, $document['data']['valid']);
        $this->assertSame(0, $document['data']['invalid']);
        $first = $document['data']['results'][0];
        $this->assertTrue($first['valid']);
        $this->assertSame('stripped', $first['kind']);
        $this->assertSame([], $first['errors']);
    }

    public function testValidateTellsWhatIsWrongWithEachConfig(): void
    {
        $this->splitTheOriginals();
        // a config without a parent file, a config that is no valid JSON, and a config that is no valid config
        $stripped = json_decode((string)file_get_contents($this->dir . '/' . self::NORTH_SEA . '.json'));
        $stripped->metadata->parent = 'nowhere';
        new Filesystem()->dumpFile($this->dir . '/A/orphan.json', ConfigDirectory::encode($stripped));
        new Filesystem()->dumpFile($this->dir . '/A/broken.json', "{\n \"a\": 1\n \"b\": 2}");
        $complete = json_decode(self::realConfigFiles()[self::NORTH_SEA]);
        unset($complete->datamodel->edition_name);
        new Filesystem()->dumpFile($this->dir . '/A/incomplete.json', ConfigDirectory::encode($complete));

        $document = $this->command('app:config:validate', [], 1);

        $this->assertSame(['validation_failed'], self::errorCodes($document));
        $this->assertSame(6, $document['data']['valid']);
        $this->assertSame(3, $document['data']['invalid']);
        $byId = array_column($document['data']['results'], null, 'id');
        $this->assertSame('parent_missing', $byId['A/orphan']['errors'][0]['code']);
        $this->assertSame('nowhere', $byId['A/orphan']['errors'][0]['details']['parent']);
        $this->assertSame('invalid_json', $byId['A/broken']['errors'][0]['code']);
        $this->assertStringContainsString('line 3, column 2', $byId['A/broken']['errors'][0]['message']);
        $this->assertSame('invalid_config', $byId['A/incomplete']['errors'][0]['code']);
        $this->assertStringContainsString('edition_name', $byId['A/incomplete']['errors'][0]['message']);
        $this->assertSame('complete', $byId['A/incomplete']['kind']);
    }

    public function testValidateChecksNamedFilesOnly(): void
    {
        $this->splitTheOriginals();

        $file = $this->dir . '/' . self::NORTH_SEA . '.json';

        $document = $this->command('app:config:validate', ['files' => [$file]], 0);

        $this->assertSame([self::NORTH_SEA], array_column($document['data']['results'], 'id'));
    }

    public function testValidateInTextModeListsWhatIsWrong(): void
    {
        $this->splitTheOriginals();
        new Filesystem()->dumpFile($this->dir . '/A/broken.json', "{\n \"a\": 1\n \"b\": 2}");

        $tester = $this->execute('app:config:validate', []);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('A/broken', self::text($tester));
        $this->assertStringContainsString('line 3, column 2', self::text($tester));
        $this->assertStringContainsString('1 of 7 config(s) are not valid', self::text($tester));
    }

    // ---------------------------------------------------------------------------------------------------- list

    public function testListTellsWhatEveryConfigAndEveryParentIs(): void
    {
        $this->splitTheOriginals();

        $document = $this->command('app:config:list', [], 0);

        $data = $document['data'];
        $this->assertSame(
            ['configs' => 6, 'parents' => 1, 'problems' => 0, 'duplicates' => 0, 'skipped' => 0],
            $data['summary']
        );
        $byId = array_column($data['configs'], null, 'id');
        $northSea = $byId[self::NORTH_SEA];
        $this->assertSame('stripped', $northSea['kind']);
        $this->assertSame('generic', $northSea['parent']);
        $this->assertSame(['generic'], $northSea['chain']);
        $this->assertSame(self::NORTH_SEA . '.json', $northSea['path']);
        $this->assertSame(94, $northSea['layers']);
        $this->assertNull($northSea['problem']);
        $parent = $data['parents'][0];
        $this->assertSame('generic', $parent['name']);
        $this->assertSame('generic.json', $parent['path']);
        $this->assertSame(array_keys($byId), $parent['children'], 'every config has this parent');
    }

    public function testListFollowsAChainOfParentsNearestFirstAndTellsWhoUsesWhom(): void
    {
        $this->splitTheOriginals();
        new Filesystem()->dumpFile($this->dir . '/groups/NS/public.json', self::emptyChildGenericJson('generic'));
        $config = json_decode((string)file_get_contents($this->dir . '/' . self::NORTH_SEA . '.json'));
        $config->metadata->parent = 'public';
        new Filesystem()->dumpFile($this->dir . '/other/ns.json', ConfigDirectory::encode($config));

        $data = $this->command('app:config:list', [], 0)['data'];

        $byId = array_column($data['configs'], null, 'id');
        $this->assertSame(['public', 'generic'], $byId['other/ns']['chain']);
        $parents = array_column($data['parents'], null, 'name');
        $this->assertSame('groups/NS/public.json', $parents['public']['path']);
        $this->assertSame('generic', $parents['public']['parent']);
        $this->assertSame(['other/ns'], $parents['public']['children']);
        $this->assertContains('groups/NS/public', $parents['generic']['children'], 'also a parent can be a child');
    }

    public function testListTellsWhatIsWrong(): void
    {
        $this->splitTheOriginals();
        $orphan = json_decode((string)file_get_contents($this->dir . '/' . self::NORTH_SEA . '.json'));
        $orphan->metadata->parent = 'nowhere';
        new Filesystem()->dumpFile($this->dir . '/A/orphan.json', ConfigDirectory::encode($orphan));
        new Filesystem()->dumpFile($this->dir . '/B/generic.json', self::emptyChildGenericJson('generic')); // twice
        new Filesystem()->dumpFile($this->dir . '/B/broken.json', '{ nope');
        new Filesystem()->dumpFile($this->dir . '/B/settings.json', '{"some": "settings"}');

        $document = $this->command('app:config:list', [], 0);

        $data = $document['data'];
        $this->assertSame(1, $data['summary']['duplicates']);
        $this->assertSame('generic', $data['duplicates'][0]['name']);
        $this->assertSame(['B/generic.json', 'generic.json'], $data['duplicates'][0]['paths']);
        $this->assertSame(['B/broken.json'], array_column($data['skipped'], 'path'));
        $byId = array_column($data['configs'], null, 'id');
        $this->assertSame('parent_missing', $byId['A/orphan']['problem']['code']);
        $this->assertSame('parent_ambiguous', $byId[self::NORTH_SEA]['problem']['code'], 'two files are generic');
        $this->assertArrayNotHasKey('B/settings', $byId, 'other JSON is not a config');
    }

    public function testListWithCheckFailsWhenSomethingIsWrongAndIsQuietWhenNot(): void
    {
        $this->splitTheOriginals();
        $this->command('app:config:list', ['--check' => true], 0);
        new Filesystem()->dumpFile($this->dir . '/B/broken.json', '{ nope');

        $document = $this->command('app:config:list', ['--check' => true], 1);

        $this->assertSame(['check_failed'], self::errorCodes($document));
        $this->assertSame(1, $document['errors'][0]['details']['skipped']);
    }

    // -------------------------------------------------------------------------------------------------- verify

    public function testVerifyTellsPerConfigWhetherItGivesTheOriginal(): void
    {
        $this->splitTheOriginals();

        $document = $this->command('app:config:verify', ['--original-dir' => $this->originalsDirectory()], 0);

        $this->assertSame(0, $document['data']['failed']);
        $this->assertCount(6, $document['data']['results']);
        $this->assertTrue($document['data']['results'][0]['same']);
        $this->assertSame([], $document['data']['results'][0]['differences']);
    }

    public function testVerifyTellsWhatDiffers(): void
    {
        $this->splitTheOriginals();
        $originals = $this->originalsDirectory();
        $changed = json_decode((string)file_get_contents($originals . '/' . self::NORTH_SEA . '.json'));
        $changed->datamodel->meta[0]->layer_tooltip = 'changed';
        new Filesystem()->dumpFile($originals . '/' . self::NORTH_SEA . '.json', ConfigDirectory::encode($changed));

        $document = $this->command('app:config:verify', ['--original-dir' => $originals], 1);

        $this->assertSame(['verify_failed'], self::errorCodes($document));
        $this->assertSame(['failed' => 1, 'total' => 6], $document['errors'][0]['details']);
        $results = array_column($document['data']['results'], null, 'id');
        $this->assertFalse($results[self::NORTH_SEA]['same']);
        $this->assertNotSame([], $results[self::NORTH_SEA]['differences']);
    }

    // --------------------------------------------------------------------------------------------------- strip

    public function testStripTellsWhatItWouldDo(): void
    {
        $this->splitTheOriginals();

        $document = $this->command('app:config:strip', [], 0);

        $this->assertSame(0, $document['data']['written']);
        $first = $document['data']['results'][0];
        $this->assertSame('already_stripped', $first['status']);
        $this->assertSame('generic', $first['parent']);
        $this->assertSame($first['nowBytes'], $first['afterBytes']);
    }

    public function testStripCheckFindsWorkToDo(): void
    {
        $this->writeOriginals();
        $this->writeGeneric();

        $document = $this->command('app:config:strip', ['--check' => true, '--parent' => 'generic'], 1);

        $this->assertSame(['check_failed'], self::errorCodes($document));
        $this->assertSame(6, $document['errors'][0]['details']['configs']);
        $this->assertSame('will_be_stripped', $document['data']['results'][0]['status']);
        $first = $document['data']['results'][0];
        $this->assertLessThan($first['nowBytes'], $first['afterBytes']);
    }

    public function testStripToldHowManyFilesItWrote(): void
    {
        $this->writeOriginals();
        $this->writeGeneric();

        $document = $this->command('app:config:strip', ['--apply' => true, '--parent' => 'generic'], 0);

        $this->assertSame(6, $document['data']['written']);
        $this->assertTrue($document['data']['apply']);
    }

    public function testStripToldWhichConfigIsInvalid(): void
    {
        $this->writeOriginals();
        $this->writeGeneric();
        new Filesystem()->dumpFile(
            $this->dir . '/A/orphan.json',
            '{"metadata": {"parent": "nowhere"}, "datamodel": {"meta": [{"layer_name": "X_A"}]}}'
        );

        $document = $this->command('app:config:strip', ['--apply' => true], 1);

        $this->assertContains('parent_missing', self::errorCodes($document));
        $this->assertSame('A/orphan', $document['errors'][0]['file']);
    }

    // --------------------------------------------------------------------------------------------------- split

    public function testSplitTellsWhatItFoundWithoutWritingAnything(): void
    {
        $this->writeOriginals();
        $before = $this->snapshot();

        $document = $this->command('app:config:split', [], 0);

        $this->assertSame($before, $this->snapshot(), 'a report writes nothing');
        $data = $document['data'];
        $this->assertFalse($data['apply']);
        $this->assertCount(6, $data['configs']);
        $this->assertSame('will_be_stripped', $data['configs'][0]['status']);
        $this->assertGreaterThan($data['configs'][0]['afterBytes'], $data['configs'][0]['nowBytes']);
        $this->assertFalse($data['generic']['existed']);
        $this->assertNull($data['generic']['nowBytes']);
        $this->assertSame('generic', $data['generic']['name']);
        $this->assertSame(96, $data['layers']['generic']);
        $this->assertContains('restrictions', array_column($data['sections'], 'name'));
        $this->assertArrayHasKey('layer_width / layer_height of layers that are not raster layers', $data['removed']);
        $this->assertSame(
            $data['total']['nowBytes'] - array_sum(array_column($data['configs'], 'nowBytes')),
            0,
            'there was no generic config, so the total now is the total of the configs'
        );
    }

    public function testSplitTellsWhatItWroteAndThatASecondRunWritesNothing(): void
    {
        $this->writeOriginals();

        $first = $this->command('app:config:split', ['--apply' => true], 0);
        $second = $this->command('app:config:split', ['--apply' => true, '--force' => true], 0);

        $this->assertTrue($first['data']['written']['generic']);
        $this->assertSame(6, $first['data']['written']['configs']);
        $this->assertSame('generic.json', $first['data']['written']['genericPath']);
        $this->assertFalse($first['data']['written']['nothingToWrite']);
        $this->assertTrue($second['data']['written']['nothingToWrite']);
        $this->assertSame(0, $second['data']['written']['configs']);
    }

    public function testSplitCheckSaysWhatWouldChange(): void
    {
        $this->writeOriginals();

        $document = $this->command('app:config:split', ['--check' => true], 1);

        $this->assertSame(['check_failed'], self::errorCodes($document));
        $this->assertTrue($document['errors'][0]['details']['genericChanged']);
        $this->assertSame(6, $document['errors'][0]['details']['configs']);
    }

    public function testSplitRefusesToOverwriteTheGenericConfigAndTellsWhere(): void
    {
        $this->writeOriginals();
        $this->command('app:config:split', ['--apply' => true], 0);

        $document = $this->command('app:config:split', ['--apply' => true], 1);

        $this->assertSame(['generic_exists'], self::errorCodes($document));
        $this->assertSame('generic.json', $document['errors'][0]['details']['path']);
    }

    public function testSplitStopsOnConfigsThatAreInvalidAndTellsWhichOnesOneByOne(): void
    {
        $this->writeOriginals();
        $complete = json_decode(self::realConfigFiles()[self::NORTH_SEA]);
        unset($complete->datamodel->edition_name);
        new Filesystem()->dumpFile($this->dir . '/A/incomplete.json', ConfigDirectory::encode($complete));
        new Filesystem()->dumpFile($this->dir . '/A/orphan.json', self::strippedJson(self::NORTH_SEA, 'nowhere'));

        $tester = $this->execute('app:config:split', ['--format' => 'json']);
        $document = $this->document($tester, 'app:config:split', 1);

        $codes = array_column($document['errors'], 'code', 'file');
        $this->assertSame('invalid_config', $codes['A/incomplete']);
        $this->assertSame('parent_missing', $codes['A/orphan']);
    }

    public function testSplitToldAboutFilesThatAreNoValidJsonAndStopsWhenWriting(): void
    {
        $this->writeOriginals();
        new Filesystem()->dumpFile($this->dir . '/Broken/Broken.json', '{ nope');

        $report = $this->command('app:config:split', [], 0);
        $apply = $this->command('app:config:split', ['--apply' => true], 1);

        $this->assertContains('file_skipped', array_column($report['warnings'], 'code'));
        $this->assertSame('Broken/Broken.json', $report['warnings'][0]['details']['path']);
        $this->assertSame(['file_not_json'], self::errorCodes($apply));
        $this->assertSame('Broken/Broken.json', $apply['errors'][0]['file']);
    }

    public function testSplitWarningsAreInTheDocument(): void
    {
        $this->writeOriginals();

        $document = $this->command('app:config:split', [], 0);

        $messages = array_column($document['warnings'], 'message');
        $this->assertNotSame([], preg_grep('/configured_routes/', $messages));
        $this->assertContains('split_warning', array_column($document['warnings'], 'code'));
    }
}
