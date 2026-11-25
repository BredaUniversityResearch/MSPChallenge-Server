<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\JsonSyntax;

class JsonSyntaxTest extends ConfigTestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function validJson(): iterable
    {
        yield 'object with everything in it' => ['{"a": [1, 2.5e3, -0, {"b": null}], "c": "x\u00e9\n", "d": true}'];
        yield 'byte order mark and white space' => ["\xEF\xBB\xBF \n {\"a\":false} \n"];
        yield 'empty containers' => ['{"a": {}, "b": []}'];
        yield 'a list' => ['[1, [2, [3]]]'];
    }

    /**
     * @dataProvider validJson
     */
    public function testValidJsonHasNoError(string $json): void
    {
        $this->assertNull(JsonSyntax::firstError($json));
        json_decode((string)preg_replace('/^\xEF\xBB\xBF/', '', $json));
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'PHP agrees that it is valid');
    }

    /**
     * @return iterable<string, array{0: string, 1: string}> JSON, and what the error has to start with and contain
     */
    public static function invalidJson(): iterable
    {
        yield 'missing comma' => ["{\n  \"a\": 1\n  \"b\": 2\n}", "line 3, column 3: ',' or '}' is expected"];
        yield 'trailing comma in an object' => ["{\n  \"a\": 1,\n}", 'line 3, column 1: a property name is expected'];
        yield 'trailing comma in a list' => ['[1, 2, ]', 'line 1, column 8: a value is expected after the comma'];
        yield 'single quotes' => ["{'a': 1}", 'line 1, column 2: a property name in double quotes is expected'];
        yield 'missing colon' => ['{"a" 1}', "line 1, column 6: ':' is expected after the property name"];
        yield 'text that is not a value' => ['{"a": tru}', 'line 1, column 7: a value is expected'];
        yield 'leading zero' => ['[01]', "line 1, column 3: ',' or ']' is expected"];
        yield 'text after the JSON' => ['{"a": 1} x', 'line 1, column 10: there is more after the end of the JSON'];
        yield 'a comment' => [
            "{\n // note\n \"a\": 1}",
            'line 2, column 2: a property name in double quotes is expected',
        ];
        yield 'line break in a string' => ["{\"a\": \"x\ny\"}", 'line 1, column 9: a line break'];
        yield 'invalid escape' => ['{"a": "x\q"}', 'line 1, column 9: this is not a valid escape sequence'];
        yield 'invalid unicode escape' => [
            '{"a": "\u12G4"}',
            'line 1, column 8: a \u escape needs 4 hexadecimal digits',
        ];
        yield 'the file ends too early' => ['{"a": [1, 2', "line 1, column 12: the file ends here, ']' is expected"];
        yield 'empty file' => ["  \n ", 'the file is empty'];
        yield 'columns count characters, not bytes' => [
            "{\"é\": 1 \"b\": 2}",
            "line 1, column 9: ',' or '}' is expected",
        ];
        yield 'an error far into the file' => [
            "{\n" . str_repeat("  \"k\": 1,\n", 40) . "  \"broken\" 2\n}",
            "line 42, column 12: ':' is expected after the property name",
        ];
    }

    /**
     * @dataProvider invalidJson
     */
    public function testTheFirstErrorIsFoundWithItsPlace(string $json, string $expected): void
    {
        $error = JsonSyntax::firstError($json);

        $this->assertNotNull($error);
        $this->assertStringStartsWith($expected, $error);
        json_decode($json);
        $this->assertNotSame(JSON_ERROR_NONE, json_last_error(), 'PHP agrees that it is invalid');
    }

    public function testTheOriginalConfigsHaveNoSyntaxError(): void
    {
        foreach (self::realConfigFiles() as $id => $contents) {
            $this->assertNull(JsonSyntax::firstError($contents), $id);
        }
    }

    public function testTheTextThatWasFoundIsShortAndOnOneLine(): void
    {
        $error = JsonSyntax::firstError("{\"a\": 1 \"b\": \"" . str_repeat('x', 500) . "\"\n}");

        $this->assertLessThan(200, strlen((string)$error));
        $this->assertStringNotContainsString("\n", (string)$error);
    }
}
