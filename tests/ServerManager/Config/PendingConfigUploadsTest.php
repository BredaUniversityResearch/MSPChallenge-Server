<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\PendingConfigUploads;

class PendingConfigUploadsTest extends ConfigCommandTestCase
{
    private function pending(int $maxAge = 3600): PendingConfigUploads
    {
        return new PendingConfigUploads($this->dir . '/pending', $maxAge);
    }

    public function testANewUploadHasATokenAndNoFiles(): void
    {
        $pending = $this->pending();

        $token = $pending->create();

        $this->assertTrue(PendingConfigUploads::isValidToken($token));
        $this->assertTrue($pending->exists($token));
        $this->assertSame([], $pending->files($token));
    }

    public function testFilesAreKeptInTheOrderTheyWereUploaded(): void
    {
        $pending = $this->pending();
        $token = $pending->create();

        $pending->add($token, ['child.json' => '{"a": 1}']);
        $pending->add($token, ['generic.json' => '{"b": 2}', 'public.json' => '{"c": 3}']);

        $this->assertSame(
            ['child.json' => '{"a": 1}', 'generic.json' => '{"b": 2}', 'public.json' => '{"c": 3}'],
            $pending->files($token)
        );
    }

    public function testAFileWithTheSameNameReplacesTheOneThatIsThere(): void
    {
        $pending = $this->pending();
        $token = $pending->create();
        $pending->add($token, ['a.json' => 'old', 'b.json' => 'b']);

        $pending->add($token, ['a.json' => 'new']);

        $this->assertSame(['a.json' => 'new', 'b.json' => 'b'], $pending->files($token));
    }

    public function testNamesCannotPointAnywhereElse(): void
    {
        $pending = $this->pending();
        $token = $pending->create();

        $pending->add($token, ['../../evil.json' => 'x', '/etc/passwd' => 'y', '123' => 'z']);

        $this->assertSame(
            ['../../evil.json' => 'x', '/etc/passwd' => 'y', '123' => 'z'],
            $pending->files($token),
            'the names are kept as they were uploaded, also names of only digits'
        );
        $this->assertFileDoesNotExist($this->dir . '/evil.json');
        $this->assertSame(
            [$token],
            array_map('basename', glob($this->dir . '/pending/*')),
            'nothing outside the upload'
        );
        foreach (glob($this->dir . '/pending/' . $token . '/*') as $stored) {
            $this->assertMatchesRegularExpression('/^(manifest|[a-f0-9]{40})\.json$/', basename($stored));
        }
    }

    public function testAnUploadCannotGetTooManyFiles(): void
    {
        $pending = $this->pending();
        $token = $pending->create();
        $files = [];
        for ($i = 0; $i < PendingConfigUploads::MAX_FILES; $i++) {
            $files['f' . $i . '.json'] = '{}';
        }
        $pending->add($token, $files);

        try {
            $pending->add($token, ['one_too_many.json' => '{}']);
            $this->fail('the upload has too many files');
        } catch (\LengthException $e) {
            $this->assertStringContainsString('at most ' . PendingConfigUploads::MAX_FILES, $e->getMessage());
        }

        $this->assertCount(PendingConfigUploads::MAX_FILES, $pending->files($token), 'nothing was added');
        $pending->add($token, ['f0.json' => 'replaced']); // replacing is fine
        $this->assertSame('replaced', $pending->files($token)['f0.json']);
    }

    public function testDiscardingRemovesTheWholeUpload(): void
    {
        $pending = $this->pending();
        $token = $pending->create();
        $pending->add($token, ['a.json' => '{}']);

        $pending->discard($token);

        $this->assertFalse($pending->exists($token));
        $this->assertSame([], $pending->files($token));
        $this->assertDirectoryDoesNotExist($this->dir . '/pending/' . $token);
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function tokens(): iterable
    {
        yield 'a token' => [str_repeat('a1', 16), true];
        yield 'too short' => ['abc', false];
        yield 'capitals' => [str_repeat('A1', 16), false];
        yield 'a path' => ['../../' . str_repeat('a', 26), false];
        yield 'empty' => ['', false];
    }

    /**
     * @dataProvider tokens
     */
    public function testOnlyTokensOfTheRightShapeAreAccepted(string $token, bool $valid): void
    {
        $this->assertSame($valid, PendingConfigUploads::isValidToken($token));
    }

    public function testAnUnknownOrInvalidTokenHasNoFilesAndDiscardingItChangesNothing(): void
    {
        $pending = $this->pending();
        $other = $this->dir . '/precious.txt';
        file_put_contents($other, 'keep');

        $pending->discard('../precious.txt');
        $pending->discard('../..');
        $pending->discard(str_repeat('0', 32));

        $this->assertFalse($pending->exists('../precious.txt'));
        $this->assertFalse($pending->exists(str_repeat('0', 32)));
        $this->assertSame([], $pending->files(str_repeat('0', 32)));
        $this->assertFileExists($other);
    }

    public function testAnUploadThatIsTooOldIsGone(): void
    {
        $pending = $this->pending(60);
        $token = $pending->create();
        $pending->add($token, ['a.json' => '{}']);
        touch($this->dir . '/pending/' . $token . '/manifest.json', time() - 120);

        $this->assertFalse($pending->exists($token));
        $this->assertDirectoryDoesNotExist($this->dir . '/pending/' . $token);
    }

    public function testPurgingRemovesOnlyTheUploadsThatAreTooOld(): void
    {
        $pending = $this->pending(60);
        $old = $pending->create();
        $recent = $pending->create();
        touch($this->dir . '/pending/' . $old . '/manifest.json', time() - 120);

        $removed = $pending->purgeExpired();

        $this->assertSame(1, $removed);
        $this->assertDirectoryDoesNotExist($this->dir . '/pending/' . $old);
        $this->assertTrue($pending->exists($recent));
        $this->assertSame(0, $this->pending()->purgeExpired(), 'nothing left to purge');
    }

    public function testPurgingWithoutAnyUploadsIsFine(): void
    {
        $this->assertSame(0, new PendingConfigUploads($this->dir . '/does/not/exist')->purgeExpired());
    }
}
