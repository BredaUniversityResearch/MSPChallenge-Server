<?php

namespace App\Domain\Config;

/**
 * Finds the file of a parent by its name, anywhere in the tree below a config root.
 *
 * A parent <name> is the file <name>.json, wherever it is. The names of the files are listed once per locator (a scan
 * of the names, no contents are read), so a chain of parents costs one scan. A locator does not outlive the use it was
 * made for: a long-lived process must make a new one, so that it notices files that came or went.
 *
 * A name that two files have is an error that names both: which of them is meant can not be told.
 */
final class ParentLocator
{
    /** @var ?array<string, string[]> name => the paths of the files that have it */
    private ?array $index = null;

    public function __construct(private readonly ConfigDirectory $directory)
    {
    }

    /**
     * @return ?string the path of <name>.json, null when no file has that name
     * @throws ConfigParentException when two files have that name
     */
    public function locate(string $name): ?string
    {
        $paths = $this->index()[$name] ?? [];
        if (count($paths) > 1) {
            sort($paths); // the same message every time
            throw new ConfigParentException(sprintf(
                'The parent "%s" is ambiguous: %s have that name. The name of a file has to be unique in the tree.',
                $name,
                implode(' and ', array_map(
                    fn(string $path) => $this->directory->relativePath($path),
                    $paths
                ))
            ));
        }
        return $paths[0] ?? null;
    }

    /**
     * @return array<string, string[]>
     */
    private function index(): array
    {
        return $this->index ??= $this->directory->jsonFileNames();
    }
}
