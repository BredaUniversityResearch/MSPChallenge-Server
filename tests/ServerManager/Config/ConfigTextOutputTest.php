<?php

namespace App\Tests\ServerManager\Config;

use App\Command\ConfigTextRenderer;
use App\Domain\Config\ConfigDirectory;
use App\Domain\Config\ConfigParents;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * What a person reads. The commands tell what they found to a document (that is what the other tests look at), and the
 * renderer writes the text from that document and nothing else. Two kinds of tests:
 *  - the text of a command is its document, rendered: it holds nothing that is not in the JSON, so a program has all
 *    that a person sees;
 *  - the words that people read: what the text says in the cases that matter (the notes, the headlines of errors, the
 *    tables).
 *
 * $this->dir is the config root. The tests that go by the words use `text()`: the display with white space collapsed.
 */
class ConfigTextOutputTest extends ConfigCommandTestCase
{
    private const string NORTH_SEA = 'North_Sea_basic/North_Sea_basic_1';

    /**
     * Runs a command in text and in JSON, and checks that the text is the document of the JSON, rendered.
     *
     * @param array<string, mixed> $input
     */
    private function assertTheTextIsTheDocument(string $command, array $input = []): void
    {
        $text = $this->execute($command, $input + ['--format' => 'text']);
        $json = $this->execute($command, $input + ['--format' => 'json']);
        $buffer = new BufferedOutput();
        $buffer->setDecorated(false);

        $style = new SymfonyStyle(new ArrayInput([]), $buffer);
        new ConfigTextRenderer()->render($command, self::documentOf($json), $style);

        $this->assertSame($buffer->fetch(), $text->getDisplay(), "the text of $command is its document, rendered");
        $this->assertSame($json->getStatusCode(), $text->getStatusCode(), 'the same exit code');
    }

    private function splitTheOriginals(): void
    {
        $this->writeOriginals();
        $tester = $this->execute('app:config:split', ['--apply' => true, '--skip-validation' => true]);
        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
    }

    /**
     * @param array<string, mixed> $input
     */
    private function words(string $command, array $input = []): string
    {
        if (in_array($command, ['app:config:split', 'app:config:strip'], true)) {
            $input += ['--skip-validation' => true]; // the originals are valid, and validating takes time
        }
        return self::text($this->execute($command, $input));
    }

    // ------------------------------------------------------------------- the text is the document, rendered

    public function testTheTextOfListIsItsDocument(): void
    {
        $this->splitTheOriginals();
        new Filesystem()->dumpFile($this->dir . '/B/generic.json', self::emptyChildGenericJson('generic'));
        new Filesystem()->dumpFile($this->dir . '/B/broken.json', '{ nope');
        new Filesystem()->dumpFile($this->dir . '/A/orphan.json', self::strippedJson(self::NORTH_SEA, 'nowhere'));

        $this->assertTheTextIsTheDocument('app:config:list');
        $this->assertTheTextIsTheDocument('app:config:list', ['--check' => true]);
    }

    public function testTheTextOfValidateIsItsDocument(): void
    {
        $this->splitTheOriginals();
        $this->assertTheTextIsTheDocument('app:config:validate');
        new Filesystem()->dumpFile($this->dir . '/A/broken.json', "{\n \"a\": 1\n \"b\": 2}");
        new Filesystem()->dumpFile($this->dir . '/A/orphan.json', self::strippedJson(self::NORTH_SEA, 'nowhere'));

        $this->assertTheTextIsTheDocument('app:config:validate');
    }

    public function testTheTextOfVerifyIsItsDocument(): void
    {
        $this->splitTheOriginals();
        $originals = $this->temporaryDirectory();
        $this->writeOriginals($originals);
        $this->assertTheTextIsTheDocument('app:config:verify', ['--original-dir' => $originals]);
        $changed = json_decode((string)file_get_contents($originals . '/' . self::NORTH_SEA . '.json'));
        $changed->datamodel->meta[0]->layer_tooltip = 'changed';
        new Filesystem()->dumpFile($originals . '/' . self::NORTH_SEA . '.json', ConfigDirectory::encode($changed));

        $this->assertTheTextIsTheDocument('app:config:verify', ['--original-dir' => $originals]);
    }

    public function testTheTextOfStripIsItsDocument(): void
    {
        $this->writeOriginals();
        $this->writeGeneric();
        $this->assertTheTextIsTheDocument('app:config:strip', ['--parent' => 'generic', '--skip-validation' => true]);
        $this->assertTheTextIsTheDocument(
            'app:config:strip',
            ['--parent' => 'generic', '--check' => true, '--skip-validation' => true]
        );
    }

    public function testTheTextOfSplitIsItsDocument(): void
    {
        $this->writeOriginals();
        $options = ['--skip-validation' => true];

        $this->assertTheTextIsTheDocument('app:config:split', $options); // a first report, no generic config yet
        $this->assertTheTextIsTheDocument('app:config:split', $options + ['--check' => true]);
        $this->execute('app:config:split', $options + ['--apply' => true, '--format' => 'json']);
        $this->assertTheTextIsTheDocument('app:config:split', $options); // what is split already
        $this->assertTheTextIsTheDocument('app:config:split', $options + ['--check' => true]);
        $this->storeAnUploadOf(self::NORTH_SEA, 'North_Sea_basic/North_Sea_basic_3');
        $this->assertTheTextIsTheDocument('app:config:split', $options); // with a copy: warnings, a larger config
    }

    public function testTheTextOfAFailureIsItsDocument(): void
    {
        $this->writeOriginals();
        new Filesystem()->dumpFile($this->dir . '/Broken/Broken.json', '{ nope');
        new Filesystem()->dumpFile($this->dir . '/A/orphan.json', self::strippedJson(self::NORTH_SEA, 'nowhere'));

        $this->assertTheTextIsTheDocument('app:config:split', ['--skip-validation' => true]);
        $this->assertTheTextIsTheDocument('app:config:split', ['--skip-validation' => true, '--apply' => true]);
        $this->assertTheTextIsTheDocument('app:config:strip', ['--parent' => 'generic']);
    }

    private function storeAnUploadOf(string $id, string $newId): void
    {
        $directory = new ConfigDirectory($this->dir);
        $stripped = $directory->read($this->dir . '/' . $id . '.json');
        $pool = ConfigParents::fromDirectory($directory)->poolOf($stripped, 'it');
        $directory->write($this->dir . '/' . $newId . '.json', self::merger()->merge($pool, $stripped));
    }

    // ------------------------------------------------------------------------- what people read: split

    public function testASplitReportHasTheTablesAndTheSizesInKb(): void
    {
        $this->writeOriginals();

        $words = $this->words('app:config:split');

        $this->assertStringContainsString('Splitting 6 configs from ' . $this->dir, $words);
        $this->assertStringContainsString('Configs (file sizes now, and as they would be written)', $words);
        $this->assertStringNotContainsString('compact JSON', $words);
        foreach (['Config', 'Layers', 'Now', 'After', 'Win'] as $header) {
            $this->assertStringContainsString($header, $words);
        }
        foreach (glob($this->dir . '/*/*.json') as $file) {
            $this->assertStringContainsString(number_format(filesize($file) / 1024, 1, '.', '') . ' kb', $words);
        }
        $this->assertMatchesRegularExpression('/\d+\.\d kb\s+\d+\.\d kb\s+-?\d+\.\d%/', $words);
        $this->assertMatchesRegularExpression('/generic\.json: \d+\.\d kb \(new\)/', $words);
        $this->assertMatchesRegularExpression(
            '/All files, with generic\.json: \d+\.\d kb now, \d+\.\d kb after \(\d+\.\d% smaller\)/',
            $words
        );
        $this->assertStringContainsString('Sections: how much is actually shared between the configs', $words);
        $this->assertStringContainsString('SEL.ship_types', $words);
        $this->assertStringContainsString('Removed / rewritten on purpose', $words);
        $this->assertStringContainsString('Dry run, nothing written. Run with --apply to write generic.json', $words);
    }

    public function testAppliedSplitSaysWhatWasWrittenAndThatASecondRunWritesNothing(): void
    {
        $this->writeOriginals();

        $first = $this->words('app:config:split', ['--apply' => true]);
        $second = $this->words('app:config:split', ['--apply' => true, '--force' => true]);

        $this->assertStringContainsString('[OK] Wrote generic.json and 6 stripped config(s)', $first);
        $this->assertStringContainsString('(replacing the existing ones, each verified)', $first);
        $this->assertStringContainsString('Nothing to write: generic.json and the 6 stripped config(s)', $second);
        $this->assertStringNotContainsString('Wrote ', $second);
        $this->assertStringContainsString('generic.json: ', $second);
        $this->assertStringContainsString('(unchanged)', $second);
        $this->assertStringContainsString('(0.0% smaller)', $second);
        $this->assertStringContainsString('6 of them are stripped already', $second);
    }

    public function testWhenOnlyTheConfigsChangeTheGenericConfigIsSaidToBeUnchanged(): void
    {
        $this->splitTheOriginals();
        $this->writeOriginals(); // the complete configs are back: they have to be stripped again

        $words = $this->words('app:config:split', ['--apply' => true, '--force' => true]);

        $this->assertStringContainsString('Wrote 6 stripped config(s)', $words);
        $this->assertStringContainsString('generic.json is unchanged', $words);
        $this->assertStringNotContainsString('Nothing to write', $words);
    }

    public function testCopiesAndVersionsAreWarnedAboutAndAConfigThatGetsLargerIsExplained(): void
    {
        $this->splitTheOriginals();
        $this->storeAnUploadOf(self::NORTH_SEA, 'North_Sea_basic/North_Sea_basic_3');
        $this->storeAnUploadOf(self::NORTH_SEA, 'North_Sea_OR_ELSE_basic/North_Sea_OR_ELSE_basic_2');

        $words = $this->words('app:config:split');

        $this->assertStringContainsString('Splitting 8 configs', $words);
        $this->assertStringContainsString('6 of them are stripped already', $words, 'the uploads are complete');
        $this->assertStringContainsString('These configs have the same content: ', $words);
        $this->assertStringContainsString('North_Sea_basic has 2 versions (North_Sea_basic_1, ', $words);
        $this->assertStringContainsString('A negative win', $words);
        $this->assertStringContainsString('Warnings', $words);
    }

    public function testASplitCheckSaysWhatWouldChangeOrThatThereIsNothing(): void
    {
        $this->writeOriginals();
        $changes = $this->words('app:config:split', ['--check' => true]);
        $this->execute('app:config:split', ['--apply' => true, '--skip-validation' => true]);
        $nothing = $this->words('app:config:split', ['--check' => true]);

        $this->assertStringContainsString('Running with --apply would change generic.json and 6 config(s).', $changes);
        $this->assertStringContainsString('[OK] Nothing to re-split.', $nothing);
    }

    public function testASplitThatCannotGoOnTellsWhichConfigsAndWhy(): void
    {
        $this->writeOriginals();
        new Filesystem()->dumpFile($this->dir . '/A/orphan.json', self::strippedJson(self::NORTH_SEA, 'nowhere'));
        new Filesystem()->dumpFile($this->dir . '/Broken/Broken.json', '{ nope');

        $report = $this->words('app:config:split');
        $apply = $this->words('app:config:split', ['--apply' => true]);

        $this->assertStringContainsString('Skipped Broken/Broken.json, not valid JSON', $report);
        $this->assertStringContainsString('Cannot continue, these configs are invalid:', $report);
        $this->assertStringContainsString('A/orphan: The parent "nowhere" of it was not found', $report);
        $this->assertStringContainsString(
            'Nothing is written, these files are not valid JSON (fix them, or leave them out with --pattern):',
            $apply
        );
        $this->assertStringContainsString('Broken/Broken.json: not valid JSON', $apply);
    }

    public function testSplitSaysThatItRefusedToOverwriteTheGenericConfig(): void
    {
        $this->splitTheOriginals();

        $words = $this->words('app:config:split', ['--apply' => true]);

        $this->assertStringContainsString('generic.json exists (it may have been edited by hand), use --force', $words);
    }

    // -------------------------------------------------------------------------- what people read: the others

    public function testStripSaysWhatItWillDoWhatItDidAndWhenThereIsNothingLeft(): void
    {
        $this->writeOriginals();
        $this->writeGeneric();
        $options = ['--parent' => 'generic'];

        $dryRun = $this->words('app:config:strip', $options);
        $check = $this->words('app:config:strip', $options + ['--check' => true]);
        $applied = $this->words('app:config:strip', $options + ['--apply' => true]);
        $nothingLeft = $this->words('app:config:strip', $options + ['--check' => true]);

        $this->assertStringContainsString('will be stripped', $dryRun);
        $this->assertStringContainsString('6 to strip, 0 already stripped, 0 kept as is.', $dryRun);
        $this->assertStringContainsString('Dry run, nothing written', $dryRun);
        $this->assertStringContainsString('6 config(s) can be stripped further', $check);
        $this->assertStringContainsString('6 config(s) stripped in place', $applied);
        $this->assertStringContainsString('Nothing left to strip', $nothingLeft);
    }

    public function testVerifySaysWhichConfigsGiveTheirOriginalAndWhichDoNot(): void
    {
        $this->splitTheOriginals();
        $originals = $this->temporaryDirectory();
        $this->writeOriginals($originals);
        $same = $this->words('app:config:verify', ['--original-dir' => $originals]);
        $changed = json_decode((string)file_get_contents($originals . '/' . self::NORTH_SEA . '.json'));
        $changed->datamodel->meta[0]->layer_tooltip = 'changed';
        new Filesystem()->dumpFile($originals . '/' . self::NORTH_SEA . '.json', ConfigDirectory::encode($changed));

        $differs = $this->words('app:config:verify', ['--original-dir' => $originals]);

        $this->assertStringContainsString('All 6 config(s) give the original config.', $same);
        $this->assertStringNotContainsString('DIFFERENT', $same);
        $this->assertStringContainsString('DIFFERENT', $differs);
        $this->assertStringContainsString('layer_tooltip differs', $differs);
        $this->assertStringContainsString('1 of 6 config(s) do not match their original.', $differs);
    }

    public function testValidateSaysWhichConfigsAreValidAndListsWhatIsWrong(): void
    {
        $this->splitTheOriginals();
        $valid = $this->words('app:config:validate');
        new Filesystem()->dumpFile($this->dir . '/A/broken.json', "{\n \"a\": 1\n \"b\": 2}");

        $invalid = $this->words('app:config:validate');

        $this->assertStringContainsString('All 6 config(s) are valid.', $valid);
        $this->assertStringContainsString('A/broken', $invalid);
        $this->assertStringContainsString('line 3, column 2', $invalid);
        $this->assertStringContainsString('1 of 7 config(s) are not valid.', $invalid);
    }

    public function testListShowsTheConfigsTheParentsAndWhatIsWrong(): void
    {
        $this->splitTheOriginals();
        new Filesystem()->dumpFile($this->dir . '/B/generic.json', self::emptyChildGenericJson('generic'));
        new Filesystem()->dumpFile($this->dir . '/B/broken.json', '{ nope');

        $words = $this->words('app:config:list');
        $check = $this->words('app:config:list', ['--check' => true]);

        $this->assertStringContainsString('Configs', $words);
        $this->assertStringContainsString('Generic configs (parents)', $words);
        $this->assertStringContainsString('Names that two files have', $words);
        $this->assertStringContainsString('generic: B/generic.json and generic.json', $words);
        $this->assertStringContainsString('Files that are not valid JSON', $words);
        $this->assertStringContainsString('B/broken.json: not valid JSON', $words);
        $this->assertStringContainsString('thing(s) are wrong: see above.', $check);
    }

    public function testListSaysSoWhenThereIsNoGenericConfigYet(): void
    {
        $this->writeOriginals(); // nothing is split: six complete configs, and no parent

        $words = $this->words('app:config:list');

        $this->assertStringContainsString('Generic configs (parents)', $words);
        $this->assertStringContainsString('None: no generic config found.', $words);
        $this->assertStringNotContainsString('Used by', $words, 'no empty table');
    }

    public function testMergePrintsTheConfigOnStdoutAndTellsWhereItWroteOnStderr(): void
    {
        $this->splitTheOriginals();
        $target = $this->temporaryDirectory() . '/final.json';

        $tester = $this->execute(
            'app:config:merge',
            ['file' => $this->dir . '/' . self::NORTH_SEA . '.json', '--output' => $target]
        );

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('[OK] Wrote ' . $target, self::text($tester));
        $this->assertFileExists($target);
    }
}
