<?php

namespace App\Domain\Config;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * The files of uploads that are not complete yet, kept until the missing files are uploaded, the upload is
 * cancelled, or the files are too old.
 *
 * An upload is a directory <directory>/<token>/. The token is random, and is only kept in the session of the user
 * who started the upload. The files are not stored under the names that were uploaded (those are only kept in a
 * list), so no name can point anywhere else.
 */
final class PendingConfigUploads
{
    /** More files than a config can have parents (a chain of parents is at most 8 levels), and some room. */
    public const int MAX_FILES = 12;
    private const string MANIFEST = 'manifest.json';

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/config_upload')]
        private readonly string $directory,
        private readonly int $maxAgeSeconds = 86400
    ) {
    }

    public static function isValidToken(string $token): bool
    {
        return preg_match('/^[a-f0-9]{32}$/', $token) === 1;
    }

    /**
     * @return string the token of a new, empty upload
     * @throws \Random\RandomException
     */
    public function create(): string
    {
        $token = bin2hex(random_bytes(16));
        new Filesystem()->mkdir($this->path($token), 0700);
        $this->writeManifest($token, []);
        return $token;
    }

    public function exists(string $token): bool
    {
        if (!self::isValidToken($token) || !is_file($this->path($token, self::MANIFEST))) {
            return false;
        }
        clearstatcache(true, $this->path($token, self::MANIFEST));
        if (time() - (int)filemtime($this->path($token, self::MANIFEST)) > $this->maxAgeSeconds) {
            $this->discard($token);
            return false;
        }
        return true;
    }

    /**
     * Adds files to an upload. A file with the name of a file that is there already replaces it.
     *
     * @param array<string, string> $files name => contents
     * @throws \LengthException when the upload would get too many files
     */
    public function add(string $token, array $files): void
    {
        $manifest = $this->manifest($token);
        foreach ($files as $name => $contents) {
            $manifest[(string)$name] = sha1((string)$name) . '.json';
        }
        if (count($manifest) > self::MAX_FILES) {
            throw new \LengthException('An upload can have at most ' . self::MAX_FILES . ' files.');
        }
        $filesystem = new Filesystem();
        foreach ($files as $name => $contents) {
            $filesystem->dumpFile($this->path($token, $manifest[(string)$name]), $contents);
        }
        $this->writeManifest($token, $manifest);
    }

    /**
     * @return array<string, string> name => contents, in the order the files were uploaded
     */
    public function files(string $token): array
    {
        $files = [];
        foreach ($this->manifest($token) as $name => $stored) {
            $contents = @file_get_contents($this->path($token, $stored));
            if ($contents !== false) {
                $files[(string)$name] = $contents;
            }
        }
        return $files;
    }

    public function discard(string $token): void
    {
        if (self::isValidToken($token)) {
            new Filesystem()->remove($this->path($token));
        }
    }

    /**
     * Removes the uploads that are too old.
     *
     * @return int how many
     */
    public function purgeExpired(): int
    {
        if (!is_dir($this->directory)) {
            return 0;
        }
        $removed = 0;
        foreach (new Finder()->directories()->in($this->directory)->depth(0) as $upload) {
            $token = $upload->getFilename();
            $manifest = $this->path($token, self::MANIFEST);
            $modified = is_file($manifest) ? (int)filemtime($manifest) : (int)$upload->getMTime();
            if (self::isValidToken($token) && time() - $modified > $this->maxAgeSeconds) {
                $this->discard($token);
                $removed++;
            }
        }
        return $removed;
    }

    /**
     * @return array<string, string> name => the name of the stored file
     */
    private function manifest(string $token): array
    {
        if (!$this->exists($token)) {
            return [];
        }
        $manifest = [];
        $decoded = json_decode((string)@file_get_contents($this->path($token, self::MANIFEST)), true);
        foreach (is_array($decoded) ? $decoded : [] as $name => $stored) {
            if (is_string($stored) && preg_match('/^[a-f0-9]{40}\.json$/', $stored) === 1) {
                $manifest[(string)$name] = $stored;
            }
        }
        return $manifest;
    }

    /**
     * @param array<string, string> $manifest
     */
    private function writeManifest(string $token, array $manifest): void
    {
        new Filesystem()->dumpFile(
            $this->path($token, self::MANIFEST),
            json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function path(string $token, string $file = ''): string
    {
        return rtrim($this->directory, '/\\') . '/' . $token . ($file === '' ? '' : '/' . $file);
    }
}
