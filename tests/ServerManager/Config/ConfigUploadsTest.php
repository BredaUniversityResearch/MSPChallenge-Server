<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\ConfigLoader;
use App\Domain\Config\ConfigUploads;
use App\Domain\Config\PendingConfigUploads;
use App\Domain\Config\SessionConfigValidator;
use App\Domain\Config\UploadProgress;

/**
 * Uploading a config together with its parents, in as many steps as it takes: what is kept, what is asked for, and
 * when the upload is processed. $this->dir is the config folder of the server.
 */
class ConfigUploadsTest extends ConfigCommandTestCase
{
    private const string ID = 'North_Sea_basic/North_Sea_basic_1';

    private string $pendingDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pendingDir = $this->temporaryDirectory();
    }

    private function uploads(int $maxAge = 3600): ConfigUploads
    {
        return new ConfigUploads(
            new ConfigLoader(
                $this->dir . '/',
                new SessionConfigValidator(self::projectDir() . '/src/Domain/SessionConfigJSONSchema.json')
            ),
            new PendingConfigUploads($this->pendingDir, $maxAge)
        );
    }

    /**
     * @return string[] the uploads that are kept
     */
    private function keptUploads(): array
    {
        return array_map('basename', glob($this->pendingDir . '/*', GLOB_ONLYDIR) ?: []);
    }

    private function assertGivesTheOriginal(UploadProgress $progress): void
    {
        $this->assertTrue($progress->isDone(), implode("\n", $progress->errors));
        $stored = ConfigFactory::json($progress->contents);
        $this->assertFalse(isset($stored->metadata->parent), 'the stored config needs no parent');
        $this->assertSame([], self::comparator()->differences(
            self::normalizer()->normalize(self::realConfigs()[self::ID]),
            $stored
        ));
    }

    public function testAConfigThatNeedsNoParentIsProcessedAtOnce(): void
    {
        $progress = $this->uploads()->submit(null, ['config.json' => self::realConfigFiles()[self::ID]]);

        $this->assertTrue($progress->isDone());
        $this->assertNotNull($progress->contents);
        $this->assertSame([], $this->keptUploads(), 'nothing is kept');
    }

    public function testAMissingParentIsAskedForAndTheFilesAreKept(): void
    {
        $uploads = $this->uploads();

        $progress = $uploads->submit(null, ['child.json' => self::strippedJson(self::ID)]);

        $this->assertTrue($progress->isWaiting());
        $this->assertSame(['child.json'], $progress->files);
        $this->assertSame([['file' => 'generic.json', 'neededBy' => 'child.json']], $progress->missing);
        $this->assertSame('Missing: generic.json, the parent of child.json.', $progress->message());
        $this->assertSame([$progress->token], $this->keptUploads());
        $again = $uploads->describe($progress->token);
        $this->assertTrue($again->isWaiting());
        $this->assertSame($progress->missing, $again->missing);
    }

    public function testTheMissingParentCompletesTheUploadAndNothingIsKept(): void
    {
        $uploads = $this->uploads();
        $waiting = $uploads->submit(null, ['child.json' => self::strippedJson(self::ID)]);

        $done = $uploads->submit($waiting->token, ['generic.json' => self::genericJson()]);

        $this->assertGivesTheOriginal($done);
        $this->assertSame([], $this->keptUploads());
        $this->assertSame(UploadProgress::IDLE, $uploads->describe($waiting->token)->state);
        $this->assertSame([], glob($this->dir . '/*.json') ?: [], 'the uploaded parent is not stored on the server');
    }

    public function testForgettingTheConfigIsAskedFor(): void
    {
        $uploads = $this->uploads();

        $waiting = $uploads->submit(null, ['generic.json' => self::genericJson()]);
        $done = $uploads->submit($waiting->token, ['child.json' => self::strippedJson(self::ID)]);

        $this->assertTrue($waiting->isWaiting());
        $this->assertTrue($waiting->missingConfig);
        $this->assertStringContainsString('The configuration itself is missing', $waiting->message());
        $this->assertGivesTheOriginal($done);
    }

    public function testParentsThatTheServerHasAreUsed(): void
    {
        $this->writeGeneric();
        $uploads = $this->uploads();

        $waiting = $uploads->submit(null, ['child.json' => self::strippedJson(self::ID, 'public')]);
        $done = $uploads->submit($waiting->token, ['public.json' => self::emptyChildGenericJson('generic')]);

        $this->assertSame([['file' => 'public.json', 'neededBy' => 'child.json']], $waiting->missing);
        $this->assertGivesTheOriginal($done);
        $this->assertSame(['generic.json'], array_map('basename', glob($this->dir . '/*.json') ?: []));
    }

    public function testAFileThatIsNoValidJsonRejectsOnlyThatSubmission(): void
    {
        $uploads = $this->uploads();
        $waiting = $uploads->submit(null, ['child.json' => self::strippedJson(self::ID)]);

        $rejected = $uploads->submit($waiting->token, ['generic.json' => "{\n \"a\": 1\n \"b\": 2}"]);

        $this->assertTrue($rejected->isRejected());
        $this->assertStringStartsWith('generic.json: Invalid JSON, line 3, column 2', $rejected->errors[0]);
        $this->assertSame(['child.json'], $rejected->files, 'what was waiting is still there');
        $this->assertSame($waiting->token, $rejected->token);
        $this->assertSame(['child.json'], array_keys($this->pendingFiles($waiting->token)));
    }

    /**
     * @return array<string, string>
     */
    private function pendingFiles(string $token): array
    {
        return new PendingConfigUploads($this->pendingDir)->files($token);
    }

    public function testASecondConfigurationIsRejectedAndNotKept(): void
    {
        $uploads = $this->uploads();
        $waiting = $uploads->submit(null, ['child.json' => self::strippedJson(self::ID)]);

        $rejected = $uploads->submit($waiting->token, ['other.json' => self::smallConfigJson(null)]);

        $this->assertTrue($rejected->isRejected());
        $this->assertStringContainsString('Upload one configuration at a time', $rejected->errors[0]);
        $this->assertSame(['child.json'], array_keys($this->pendingFiles($waiting->token)));
    }

    public function testCancellingThrowsEverythingAway(): void
    {
        $uploads = $this->uploads();
        $waiting = $uploads->submit(null, ['child.json' => self::strippedJson(self::ID)]);

        $uploads->cancel($waiting->token);

        $this->assertSame([], $this->keptUploads());
        $this->assertSame(UploadProgress::IDLE, $uploads->describe($waiting->token)->state);
        $uploads->cancel(null); // nothing to cancel is fine
        $uploads->cancel(str_repeat('0', 32));
        $this->addToAssertionCount(1);
    }

    public function testAFileWithTheSameNameReplacesTheOneThatIsWaiting(): void
    {
        $uploads = $this->uploads();
        $first = $uploads->submit(null, ['child.json' => self::strippedJson(self::ID)]);

        $second = $uploads->submit($first->token, ['child.json' => self::strippedJson(self::ID, 'other_parent')]);

        $this->assertTrue($second->isWaiting());
        $this->assertSame(['child.json'], $second->files);
        $this->assertSame([['file' => 'other_parent.json', 'neededBy' => 'child.json']], $second->missing);
    }

    public function testACompleteUploadThatIsNoValidConfigIsRejectedAndNotKept(): void
    {
        $config = ConfigFactory::copy(self::realConfigs()[self::ID]);
        unset($config->datamodel->edition_name);

        $rejected = $this->uploads()->submit(null, ['bad.json' => json_encode($config)]);

        $this->assertTrue($rejected->isRejected());
        $this->assertStringContainsString('edition_name', implode(' ', $rejected->errors));
        $this->assertSame([], $this->keptUploads());
    }

    public function testAParentThatMakesTheConfigInvalidIsRejectedAndTheUploadKeepsWaiting(): void
    {
        $uploads = $this->uploads();
        $waiting = $uploads->submit(null, ['child.json' => self::strippedJson(self::ID)]);
        $parent = json_decode(self::genericJson());
        $parent->datamodel->simulation_settings->CEL = null; // a config needs its CEL

        $rejected = $uploads->submit($waiting->token, ['generic.json' => json_encode($parent)]);
        $done = $uploads->submit($waiting->token, ['generic.json' => self::genericJson()]);

        $this->assertTrue($rejected->isRejected());
        $this->assertSame(['child.json'], $rejected->files);
        $this->assertGivesTheOriginal($done);
    }

    public function testTooManyFilesAreRejected(): void
    {
        $files = [];
        for ($i = 0; $i <= PendingConfigUploads::MAX_FILES; $i++) {
            $files['f' . $i . '.json'] = self::genericJson();
        }

        $rejected = $this->uploads()->submit(null, $files);

        $this->assertTrue($rejected->isRejected());
        $this->assertSame(
            ['An upload can have at most ' . PendingConfigUploads::MAX_FILES . ' files.'],
            $rejected->errors
        );
        $this->assertSame([], $this->keptUploads());
    }

    public function testAnUploadThatBecameCompleteIsFinishedWithoutNewFiles(): void
    {
        $uploads = $this->uploads();
        $waiting = $uploads->submit(null, ['child.json' => self::strippedJson(self::ID)]);
        $this->writeGeneric(); // the server has the parent now

        $described = $uploads->describe($waiting->token);
        $done = $uploads->submit($waiting->token, []);

        $this->assertTrue($described->isWaiting());
        $this->assertSame([], $described->missing, 'nothing is missing any more');
        $this->assertGivesTheOriginal($done);
        $this->assertSame([], $this->keptUploads());
    }

    public function testAnUploadThatIsTooOldIsForgotten(): void
    {
        $waiting = $this->uploads(60)->submit(null, ['child.json' => self::strippedJson(self::ID)]);
        touch($this->pendingDir . '/' . $waiting->token . '/manifest.json', time() - 120);

        $uploads = $this->uploads(60);

        $this->assertSame(UploadProgress::IDLE, $uploads->describe($waiting->token)->state);
        $fresh = $uploads->submit($waiting->token, ['generic.json' => self::genericJson()]);
        $this->assertTrue($fresh->isWaiting(), 'a new upload: the config is missing again');
        $this->assertTrue($fresh->missingConfig);
        $this->assertNotSame($waiting->token, $fresh->token);
    }

    public function testATokenThatIsNotOursIsIgnored(): void
    {
        $progress = $this->uploads()->submit('../../etc', ['generic.json' => self::genericJson()]);

        $this->assertTrue($progress->isWaiting());
        $this->assertTrue(PendingConfigUploads::isValidToken((string)$progress->token));
        $this->assertSame('', trim((string)$progress->token, 'a0123456789abcdef'));
    }

    public function testAnUnusedParentDoesNotStopTheUpload(): void
    {
        $progress = $this->uploads()->submit(null, [
            'child.json' => self::strippedJson(self::ID),
            'generic.json' => self::genericJson(),
            'spare.json' => self::emptyChildGenericJson('generic'),
        ]);

        $this->assertGivesTheOriginal($progress);
    }
}
