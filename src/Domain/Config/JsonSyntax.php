<?php

namespace App\Domain\Config;

/**
 * Finds the first syntax error in a piece of JSON, with its place. json_decode() only says "Syntax error".
 *
 * This is a strict JSON parser (RFC 8259) that checks and does not build anything: no trailing commas, no comments,
 * double quoted strings only.
 */
final class JsonSyntax
{
    private const int MAX_DEPTH = 512;

    private int $position = 0;
    private readonly int $length;

    private function __construct(private readonly string $json)
    {
        $this->length = strlen($json);
    }

    /**
     * @return ?string the first error, for example "line 12, column 5: ',' or '}' is expected, found "layer_name""
     *         (columns count characters), or null when the JSON is valid
     */
    public static function firstError(string $json): ?string
    {
        $parser = new self($json);
        if (str_starts_with($json, "\xEF\xBB\xBF")) {
            $parser->position = 3; // a byte order mark is not part of the JSON
        }
        $parser->skipWhitespace();
        if ($parser->position >= $parser->length) {
            return 'the file is empty';
        }
        if (null !== $error = $parser->value(0)) {
            return $error;
        }
        $parser->skipWhitespace();
        return $parser->position < $parser->length
            ? $parser->fail('there is more after the end of the JSON')
            : null;
    }

    private function value(int $depth): ?string
    {
        if ($depth > self::MAX_DEPTH) {
            return $this->fail('the nesting is too deep');
        }
        $this->skipWhitespace();
        $character = $this->peek();
        return match (true) {
            $character === '' => $this->fail('the file ends here, a value is expected'),
            $character === '{' => $this->object($depth),
            $character === '[' => $this->list($depth),
            $character === '"' => $this->string(),
            $character === '-' || ($character >= '0' && $character <= '9') => $this->number(),
            default => $this->literal(),
        };
    }

    private function object(int $depth): ?string
    {
        $this->position++;
        $this->skipWhitespace();
        if ($this->peek() === '}') {
            $this->position++;
            return null;
        }
        while (true) {
            $this->skipWhitespace();
            if ($this->peek() !== '"') {
                return $this->fail(match ($this->peek()) {
                    '}' => 'a property name is expected after the comma (a comma before } is not allowed)',
                    '' => 'the file ends here, a property name is expected',
                    default => 'a property name in double quotes is expected',
                });
            }
            if (null !== $error = $this->string()) {
                return $error;
            }
            $this->skipWhitespace();
            if ($this->peek() !== ':') {
                return $this->fail("':' is expected after the property name");
            }
            $this->position++;
            if (null !== $error = $this->value($depth + 1)) {
                return $error;
            }
            $this->skipWhitespace();
            $next = $this->peek();
            if ($next === ',') {
                $this->position++;
                continue;
            }
            if ($next === '}') {
                $this->position++;
                return null;
            }
            return $this->fail($next === '' ? "the file ends here, '}' is expected" : "',' or '}' is expected");
        }
    }

    private function list(int $depth): ?string
    {
        $this->position++;
        $this->skipWhitespace();
        if ($this->peek() === ']') {
            $this->position++;
            return null;
        }
        while (true) {
            if ($this->peek() === ']') {
                return $this->fail('a value is expected after the comma (a comma before ] is not allowed)');
            }
            if (null !== $error = $this->value($depth + 1)) {
                return $error;
            }
            $this->skipWhitespace();
            $next = $this->peek();
            if ($next === ',') {
                $this->position++;
                $this->skipWhitespace();
                continue;
            }
            if ($next === ']') {
                $this->position++;
                return null;
            }
            return $this->fail($next === '' ? "the file ends here, ']' is expected" : "',' or ']' is expected");
        }
    }

    private function string(): ?string
    {
        $start = $this->position;
        $this->position++;
        while ($this->position < $this->length) {
            $character = $this->json[$this->position];
            if ($character === '"') {
                $this->position++;
                return null;
            }
            if ($character === '\\') {
                $escaped = $this->json[$this->position + 1] ?? '';
                if ($escaped === 'u') {
                    if (!preg_match('/\G[0-9a-fA-F]{4}/', $this->json, $match, 0, $this->position + 2)) {
                        return $this->fail('a \u escape needs 4 hexadecimal digits');
                    }
                    $this->position += 6;
                    continue;
                }
                if (!in_array($escaped, ['"', '\\', '/', 'b', 'f', 'n', 'r', 't'], true)) {
                    return $this->fail('this is not a valid escape sequence in a string');
                }
                $this->position += 2;
                continue;
            }
            if (ord($character) < 0x20) {
                return $this->fail('a line break or other control character inside a string (write \n instead)');
            }
            $this->position++;
        }
        $this->position = $start;
        return $this->fail('this string is never closed');
    }

    private function number(): ?string
    {
        $pattern = '/\G-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/';
        if (!preg_match($pattern, $this->json, $match, 0, $this->position)) {
            return $this->fail('this is not a valid number');
        }
        $this->position += strlen($match[0]);
        return null;
    }

    private function literal(): ?string
    {
        foreach (['true', 'false', 'null'] as $word) {
            if (substr_compare($this->json, $word, $this->position, strlen($word)) === 0) {
                $this->position += strlen($word);
                return null;
            }
        }
        return $this->fail('a value is expected (text, number, true, false, null, { or [)');
    }

    private function skipWhitespace(): void
    {
        while ($this->position < $this->length && strpbrk($this->json[$this->position], " \t\n\r") !== false) {
            $this->position++;
        }
    }

    private function peek(): string
    {
        return $this->json[$this->position] ?? '';
    }

    private function fail(string $message): string
    {
        $before = substr($this->json, 0, $this->position);
        $lineStart = strrpos($before, "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $line = substr_count($before, "\n") + 1;
        $beforeOnLine = substr($before, $lineStart);
        $column = (function_exists('mb_strlen') ? mb_strlen($beforeOnLine, 'UTF-8') : strlen($beforeOnLine)) + 1;
        $found = '';
        if ($this->position < $this->length) {
            $rest = substr($this->json, $this->position, 20);
            $found = ', found "' . rtrim(strtok($rest, "\r\n") ?: $rest) . '"';
        }
        return sprintf('line %d, column %d: %s%s', $line, $column, $message, $found);
    }
}
