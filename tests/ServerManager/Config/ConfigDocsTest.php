<?php

namespace App\Tests\ServerManager\Config;

use PHPUnit\Framework\TestCase;

/**
 * The diagrams of docs/config-simplification-impl-design are Mermaid. Each one is a file in diagrams/, and the same
 * text is in the document, where it is shown as a diagram. Two copies can differ: this test fails when they do.
 */
class ConfigDocsTest extends TestCase
{
    private const string FOLDER = 'docs/config-simplification-impl-design';
    private const string DOCUMENT = 'config-simplification-implementation-design.md';

    public function testTheDiagramsInTheDocumentAreTheFilesOfTheDiagramsFolder(): void
    {
        $folder = dirname(__DIR__, 3) . '/' . self::FOLDER;
        if (!is_file($folder . '/' . self::DOCUMENT)) {
            $this->markTestSkipped(self::FOLDER . ' is not in this project');
        }
        $document = str_replace("\r\n", "\n", (string)file_get_contents($folder . '/' . self::DOCUMENT));
        $files = glob($folder . '/diagrams/*.mmd') ?: [];

        $this->assertGreaterThanOrEqual(2, count($files), 'the diagrams that the document is made with');
        foreach ($files as $file) {
            $name = basename($file);
            $source = rtrim(str_replace("\r\n", "\n", (string)file_get_contents($file)));
            $this->assertStringContainsString(
                "```mermaid\n" . $source . "\n```",
                $document,
                "$name is in the document, as it is in the file"
            );
            $this->assertStringContainsString("(diagrams/$name)", $document, "the document links to $name");
        }
        $this->assertSame(
            count($files),
            substr_count($document, "```mermaid\n"),
            'every diagram in the document is a file in diagrams/'
        );
    }
}
