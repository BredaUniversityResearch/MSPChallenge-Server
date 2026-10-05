<?php

namespace App\Domain\Config;

use App\Domain\Config\Merge\RegionConfigMerger;
use App\Domain\Config\Split\ConfigValues;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * The folder with the session configs: <root>/<folder>/<name>.json, next to the generic configs (parents) in
 * <root>/<name>.json. Knows how to find and read them, and how to replace a file safely.
 */
final class ConfigDirectory
{
    /** The name of the generic config the commands use when they are not told another one. */
    public const string DEFAULT_GENERIC = 'generic';

    public function __construct(private readonly string $root)
    {
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * The file of a generic config, which other configs have as their parent: <root>/<name>.json
     */
    /**
     * Where a NEW parent goes: in the root. (Where an existing one is, is told by locateParent().)
     */
    public function parentPath(string $name): string
    {
        return $this->root . '/' . $name . '.json';
    }

    public function genericPath(string $name = self::DEFAULT_GENERIC): string
    {
        return $this->parentPath($name);
    }

    /**
     * Names of parents are file names without the extension: letters, digits, "_" and "-". A parent is always a
     * file in the root of the config folder, so a name cannot point anywhere else.
     */
    public static function isValidParentName(string $name): bool
    {
        return preg_match('/^[A-Za-z0-9_-]+$/', $name) === 1;
    }

    /**
     * $path as it is best shown to a user: relative to $base when it is inside it, otherwise in full.
     * Path::makeRelative() throws when the paths have different roots, for example on Windows when the project is on
     * drive D: and the temporary directory (or any other place) is on drive C:.
     */
    public static function displayPath(string $path, string $base): string
    {
        return Path::isBasePath($base, $path) ? Path::makeRelative($path, $base) : Path::canonicalize($path);
    }

    /**
     * The configs, complete or stripped, anywhere below the root: a JSON file with layers that is not a generic
     * config. Generic configs (the parents), any other JSON, and the leftover *.region.json files of the staging phase
     * are not configs. A file that is not valid JSON can not be told: it is left out and reported in $skipped.
     *
     * @param array<string, string> $skipped receives the files that were left out because they are not valid JSON:
     *        absolute path => why
     * @return array<string, string> config id (the path below the root, without .json) => absolute path, sorted by id
     */
    public function configFiles(string $pattern = '*.json', array &$skipped = []): array
    {
        $scan = $this->scan($pattern);
        foreach ($scan['skipped'] as $path => $why) {
            $skipped[$path] = $why;
        }
        return array_map(static fn(array $config) => $config['path'], $scan['configs']);
    }

    /**
     * Every JSON file with layers below the root, told apart by what it is: a config, or a generic config (a parent).
     * Other JSON is left out. The contents of the files are read, but only what is needed to tell about them is kept.
     *
     * @return array{
     *     configs: array<string, array{
     *         path: string, layers: int, parent: ?string, parentError: ?array<string, string>, stripped: bool
     *     }>,
     *     generics: array<string, array{
     *         path: string, layers: int, parent: ?string, parentError: ?array<string, string>, name: string
     *     }>,
     *     skipped: array<string, string>
     * } configs and generics by id (the path below the root, without .json), sorted by id; the files that are not
     *   valid JSON by absolute path => why. "parentError" is a code and a message when metadata.parent is no good.
     */
    public function scan(string $pattern = '*.json'): array
    {
        $configs = [];
        $generics = [];
        $skipped = [];
        $finder = new Finder()->files()->in($this->root)->name($pattern)->notName('*.region.json')->sortByName();
        foreach ($finder as $file) {
            $path = $file->getRealPath() ?: $file->getPathname(); // always a string
            try {
                $document = $this->read($path);
            } catch (\JsonException | \RuntimeException $e) {
                // it can not be told what it is: it is left alone, and said
                $skipped[$path] = 'not valid JSON: ' . strtok($e->getMessage(), "\n");
                continue;
            }
            $datamodel = $document->datamodel ?? null;
            if (!$datamodel instanceof \stdClass || !is_array($datamodel->meta ?? null)) {
                continue; // some other JSON
            }
            $id = (string)preg_replace('/\.json$/', '', str_replace('\\', '/', $file->getRelativePathname()));
            $record = ['path' => $path, 'layers' => count($datamodel->meta), 'parent' => null, 'parentError' => null];
            try {
                $record['parent'] = ConfigParents::parentOf($document);
            } catch (ConfigParentException $e) {
                $record['parentError'] = ['code' => $e->reason, 'message' => $e->getMessage()];
            }
            if (ConfigParents::isGeneric($document)) {
                $generics[$id] = $record + ['name' => $file->getBasename('.json')];
            } else {
                $configs[$id] = $record + ['stripped' => RegionConfigMerger::isStripped($document)];
            }
        }
        ksort($configs);
        ksort($generics);
        return ['configs' => $configs, 'generics' => $generics, 'skipped' => $skipped];
    }

    /**
     * The names of all JSON files below the root, without the extension (a scan of the names only).
     *
     * @return array<string, string[]> name => the paths of the files that have it
     */
    public function jsonFileNames(): array
    {
        $names = [];
        if (!is_dir($this->root)) {
            return $names;
        }
        foreach (new Finder()->files()->in($this->root)->name('*.json')->sortByName() as $file) {
            $names[$file->getBasename('.json')][] = $file->getRealPath() ?: $file->getPathname();
        }
        return $names;
    }

    /**
     * The path of a parent: the file <name>.json anywhere below the root. Every call looks at the files again.
     *
     * @throws ConfigParentException when two files have that name
     */
    public function locateParent(string $name): ?string
    {
        return new ParentLocator($this)->locate($name);
    }

    /**
     * The path that a path is known by to users: relative to the root.
     */
    public function relativePath(string $path): string
    {
        return Path::isBasePath($this->root, $path) ? Path::makeRelative($path, $this->root) : $path;
    }

    /**
     * The files named on the command line, or all configs of the directory when none are named. A file inside
     * the directory gets its path below the root as its id (without .json), any other file its name.
     *
     * @param string[] $arguments paths, absolute or relative to $baseDir
     * @return array<string, string> id => absolute path
     * @throws \RuntimeException when a named file does not exist
     */
    public function resolveFiles(
        array $arguments,
        string $baseDir,
        string $pattern = '*.json',
        array &$skipped = []
    ): array {
        if ($arguments === []) {
            return $this->configFiles($pattern, $skipped);
        }
        $files = [];
        foreach ($arguments as $argument) {
            $path = Path::makeAbsolute($argument, $baseDir);
            if (!is_file($path)) {
                throw new \RuntimeException("File not found: $argument");
            }
            $relative = Path::isBasePath($this->root, $path)
                ? Path::makeRelative($path, $this->root)
                : basename($path);
            $files[preg_replace('/\.json$/', '', $relative)] = $path;
        }
        return $files;
    }

    /**
     * @throws \JsonException
     * @throws \RuntimeException when the file cannot be read or is not a JSON object
     */
    public function read(string $path): \stdClass
    {
        $fingerprint = '';
        return $this->readFingerprinted($path, $fingerprint);
    }

    /**
     * Reads a file once, and gives the fingerprint of the bytes that were read along with the document, so the
     * fingerprint is the one of exactly the document that is returned, also when the file changes at the same moment.
     *
     * @param string $fingerprint set to the fingerprint of the contents of the file
     * @throws \RuntimeException
     * @throws \JsonException
     */
    public function readFingerprinted(string $path, string &$fingerprint): \stdClass
    {
        if (false === $contents = @file_get_contents($path)) {
            throw new \RuntimeException("Cannot read $path");
        }
        $fingerprint = self::fingerprint($contents);
        $document = json_decode(ltrim($contents, "\xEF\xBB\xBF"), false, 512, JSON_THROW_ON_ERROR);
        if (!$document instanceof \stdClass) {
            throw new \RuntimeException("$path does not contain a JSON object");
        }
        return $document;
    }

    /**
     * A short hash of contents: the same contents always give the same fingerprint, and a change is never missed
     * (unlike a modification time, that has a resolution of a second and that git, rsync and Docker volumes keep).
     */
    public static function fingerprint(string $contents): string
    {
        return hash('xxh128', $contents);
    }

    public function hasGeneric(string $name = self::DEFAULT_GENERIC): bool
    {
        return $this->locateParent($name) !== null;
    }

    /**
     * @throws \RuntimeException when there is no such file, or it is not valid JSON
     * @throws \JsonException
     */
    public function loadGeneric(string $name = self::DEFAULT_GENERIC): \stdClass
    {
        $path = $this->locateParent($name) ?? throw new \RuntimeException(
            'Missing ' . $this->parentPath($name) . '. Create it with app:config:split --apply.'
        );
        return $this->read($path);
    }

    /**
     * Pretty printed with 2 spaces per level, like the existing configs (PHP itself indents with 4, which would
     * make every file noticeably bigger and every diff with an original unreadable).
     *
     * @throws \JsonException
     */
    public static function encode(\stdClass $document): string
    {
        $json = json_encode($document, ConfigValues::ENCODE_FLAGS);
        // only indentation starts a line with spaces: a newline inside a string value is always escaped
        $json = preg_replace_callback(
            '/^ +/m',
            static fn(array $match) => str_repeat(' ', intdiv(strlen($match[0]), 2)),
            $json
        );
        return $json . "\n";
    }

    /**
     * Replaces $path with $document, but only when the file as written passes $verify: the document is
     * written to a temporary file next to $path, read back, verified, and only then renamed over $path. If the
     * verification fails (or anything else goes wrong) $path is left as it was and the temporary file is
     * removed.
     *
     * @param \Closure(\stdClass): string[] $verify gets the document as read back, returns the problems found
     * @return string[] the problems, empty when $path was replaced
     * @throws \JsonException
     */
    public function replaceVerified(string $path, \stdClass $document, \Closure $verify): array
    {
        [$problems, $temporary] = $this->stage($path, $document, $verify);
        if ($temporary !== null) {
            $this->commit($temporary, $path);
        }
        return $problems;
    }

    /**
     * First half of replaceVerified(): writes $document to a temporary file next to $path, reads it back and
     * verifies it. Nothing is replaced yet, so several files can be staged and checked before the first one is
     * committed.
     *
     * @param \Closure(\stdClass): string[] $verify
     * @return array{0: string[], 1: ?string} the problems, and the temporary file (null when there were problems;
     *         then it has been removed already)
     */
    public function stage(string $path, \stdClass $document, \Closure $verify): array
    {
        $filesystem = new Filesystem();
        $temporary = $path . '.tmp' . bin2hex(random_bytes(4));
        try {
            $filesystem->dumpFile($temporary, self::encode($document));
            $problems = $verify($this->read($temporary));
        } catch (\Throwable $e) {
            $problems = ['Could not write ' . $path . ': ' . $e->getMessage()];
        }
        if ($problems !== []) {
            $filesystem->remove($temporary);
            return [$problems, null];
        }
        return [[], $temporary];
    }

    /**
     * Second half: puts a staged file in place of $path.
     */
    public function commit(string $temporary, string $path): void
    {
        new Filesystem()->rename($temporary, $path, true);
    }

    /**
     * Removes staged files that will not be committed.
     */
    public function discard(string ...$temporaries): void
    {
        new Filesystem()->remove($temporaries);
    }

    /**
     * Writes a new file (or replaces one) atomically, without verification.
     *
     * @throws \JsonException
     */
    public function write(string $path, \stdClass $document): void
    {
        new Filesystem()->dumpFile($path, self::encode($document));
    }
}
