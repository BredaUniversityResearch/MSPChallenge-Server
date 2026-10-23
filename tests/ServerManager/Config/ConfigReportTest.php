<?php

namespace App\Tests\ServerManager\Config;

use App\Command\ConfigReport;
use App\Domain\Config\ConfigParentException;
use App\Domain\Config\InvalidSessionConfigException;
use PHPUnit\Framework\TestCase;

/**
 * The codes that the config tools give to what went wrong (documented in docs/config-tools-cli.md).
 */
class ConfigReportTest extends TestCase
{
    public function testAProblemWithAParentKeepsItsReasonAndDetails(): void
    {
        $error = ConfigReport::describe(
            new ConfigParentException('no parent', ConfigParentException::MISSING, ['parent' => 'x']),
            'a/b'
        );

        $this->assertSame(
            ['code' => 'parent_missing', 'message' => 'no parent', 'file' => 'a/b', 'details' => ['parent' => 'x']],
            $error
        );
    }

    public function testAnExceptionWithoutDetailsHasNoDetailsKey(): void
    {
        $error = ConfigReport::describe(new ConfigParentException('loop', ConfigParentException::LOOP));

        $this->assertSame(['code' => 'parent_loop', 'message' => 'loop'], $error);
    }

    public function testASyntaxErrorIsToldApartFromAnInvalidConfig(): void
    {
        $syntax = ConfigReport::describe(new InvalidSessionConfigException(['Invalid JSON, line 3, column 2: x']));
        $schema = ConfigReport::describe(new InvalidSessionConfigException(['[datamodel] the property is required']));

        $this->assertSame('invalid_json', $syntax['code']);
        $this->assertSame('invalid_config', $schema['code']);
        $this->assertSame(['[datamodel] the property is required'], $schema['details']['errors']);
    }

    /**
     * @return iterable<string, array{0: \Throwable, 1: string}>
     */
    public static function throwables(): iterable
    {
        yield 'a JSON exception' => [new \JsonException('Syntax error'), 'invalid_json'];
        yield 'a file that is not there' => [new \RuntimeException('File not found: x.json'), 'file_not_found'];
        yield 'a file that can not be read' => [new \RuntimeException('Cannot read /x/y.json'), 'file_unreadable'];
        yield 'a file that is no object' => [
            new \RuntimeException('/x/y.json does not contain a JSON object'),
            'invalid_json',
        ];
        yield 'something else' => [new \LogicException("first line\nsecond line"), 'error'];
    }

    /**
     * @dataProvider throwables
     */
    public function testEveryExceptionGetsACode(\Throwable $e, string $code): void
    {
        $error = ConfigReport::describe($e);

        $this->assertSame($code, $error['code']);
        $this->assertStringNotContainsString("\n", $error['message'], 'the first line of the message');
    }

    public function testEveryReasonOfAParentProblemIsAConstantWithAStableValue(): void
    {
        $reasons = [
            ConfigParentException::MISSING, ConfigParentException::AMBIGUOUS, ConfigParentException::LOOP,
            ConfigParentException::TOO_DEEP, ConfigParentException::NOT_GENERIC, ConfigParentException::INVALID_NAME,
            ConfigParentException::UNREADABLE, ConfigParentException::REQUIRED,
        ];

        $this->assertSame([
            'parent_missing', 'parent_ambiguous', 'parent_loop', 'parent_too_deep', 'parent_not_generic',
            'parent_name_invalid', 'parent_unreadable', 'parent_required',
        ], $reasons);
    }
}
