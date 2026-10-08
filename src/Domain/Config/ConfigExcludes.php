<?php

namespace App\Domain\Config;

/**
 * What is left out when a config folder is read: folders and files that are not part of it (old configs, copies,
 * notes). A file that is left out is not seen at all: it is not a config, it is not a parent (also not when a config
 * names it), and it does not make two files with the same name.
 *
 * A pattern is one of two kinds:
 *  - a name, without a "/": it is left out when a folder or the file anywhere below the root has that name
 *    (NotMaintained, *_old.json, CS_Basic). The name of a file can be written with or without .json.
 *  - a path, with a "/", from the root: it is left out when the path of a file, or the path of a folder that the file
 *    is in, matches (NS/old, NS/*_copy.json). A path also matches without .json at the end.
 * In a pattern "*" is any characters except "/", "?" is one character except "/", and "**" is any characters, also
 * "/" ("**" followed by "/" can also be no folder at all: **\/x is x at the root too). Backslashes are slashes. On
 * Windows the case of the letters does not matter, as on its file system.
 */
final class ConfigExcludes
{
    /** @var list<array{regex: string, anywhere: bool}> */
    private array $rules = [];

    /**
     * @param string[] $patterns
     */
    public function __construct(array $patterns = [])
    {
        foreach ($patterns as $pattern) {
            $pattern = trim(trim(str_replace('\\', '/', $pattern)), '/');
            if ($pattern === '') {
                continue;
            }
            $this->rules[] = ['regex' => self::toRegex($pattern), 'anywhere' => !str_contains($pattern, '/')];
        }
    }

    public function isEmpty(): bool
    {
        return $this->rules === [];
    }

    /**
     * Is a file left out? Its path is given from the root (slashes or backslashes).
     */
    public function excludes(string $relativePath): bool
    {
        if ($this->rules === []) {
            return false;
        }
        $segments = explode('/', trim(str_replace('\\', '/', $relativePath), '/'));
        $count = count($segments);
        foreach ($this->rules as $rule) {
            if ($rule['anywhere']) {
                foreach ($segments as $position => $segment) {
                    if (self::matches($rule['regex'], $segment, $position === $count - 1)) {
                        return true;
                    }
                }
                continue;
            }
            for ($length = 1; $length <= $count; $length++) {
                $path = implode('/', array_slice($segments, 0, $length));
                if (self::matches($rule['regex'], $path, $length === $count)) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function matches(string $regex, string $name, bool $isFile): bool
    {
        if (preg_match($regex, $name) === 1) {
            return true;
        }
        // a file is also found by its name without .json
        return $isFile && str_ends_with($name, '.json') && preg_match($regex, substr($name, 0, -5)) === 1;
    }

    private static function toRegex(string $pattern): string
    {
        $regex = '';
        $length = strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            $character = $pattern[$i];
            if ($character === '*' && ($pattern[$i + 1] ?? '') === '*') {
                if (($pattern[$i + 2] ?? '') === '/') {
                    $regex .= '(?:.*/)?';
                    $i += 2;
                } else {
                    $regex .= '.*';
                    $i += 1;
                }
            } elseif ($character === '*') {
                $regex .= '[^/]*';
            } elseif ($character === '?') {
                $regex .= '[^/]';
            } else {
                $regex .= preg_quote($character, '#');
            }
        }
        return '#^' . $regex . '$#' . (PHP_OS_FAMILY === 'Windows' ? 'iu' : 'u');
    }
}
