<?php

namespace App\Domain\Services;

use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use League\CommonMark\Node\StringContainerHelper;

class TosDocumentService
{
    private const BASE_DIR = 'docs/TOS';

    public function __construct(private readonly string $projectDir)
    {
    }

    /**
     * Resolves and validates a relative path stays inside docs/TOS. Throws if it escapes.
     */
    public function resolvePath(string $relativePath): string
    {
        $baseRealPath = realpath($this->projectDir . '/' . self::BASE_DIR);
        $targetRealPath = realpath($this->projectDir . '/' . ltrim($relativePath, '/'));

        if (false === $baseRealPath || false === $targetRealPath
            || !str_starts_with($targetRealPath, $baseRealPath)
        ) {
            throw new NotFoundHttpException('Requested document is not a valid TOS document.');
        }

        return $targetRealPath;
    }

    /**
     * Renders a document to HTML, rewriting relative .md links so the front-end
     * can intercept them as "open as tab" instead of full navigation.
     * @throws CommonMarkException
     */
    public function renderDocument(string $relativePath): string
    {
        $absolutePath = $this->resolvePath($relativePath);
        $markdown = file_get_contents($absolutePath);
        $html = (string) $this->getConverter()->convert($markdown);

        return $this->rewriteRelativeLinks($html, $relativePath);
    }

    /**
     * Extracts the HTML of the section under a heading matching $headingPattern (a full
     * preg pattern, including delimiters), up to (not including) the next heading of the
     * same or shallower level. Returns null if no matching heading is found.
     * @throws CommonMarkException
     */
    public function extractSection(string $relativePath, string $headingPattern): ?string
    {
        $absolutePath = $this->resolvePath($relativePath);
        $lines = file($absolutePath, FILE_IGNORE_NEW_LINES);
        $converter = $this->getConverter();
        $document = $converter->convert(implode("\n", $lines))->getDocument();

        $startLine = null;
        $headingLevel = null;
        $endLine = count($lines);

        foreach ($document->iterator() as $node) {
            if (!$node instanceof Heading) {
                continue;
            }

            if (null !== $startLine && $node->getLevel() <= $headingLevel) {
                $endLine = $node->getStartLine() - 1;
                break;
            }

            if (null === $startLine) {
                $matchResult = @preg_match($headingPattern, trim(StringContainerHelper::getChildText($node)));
                if (false === $matchResult) {
                    throw new \RuntimeException("Invalid acknowledgment heading pattern: {$headingPattern}");
                }
                if (1 === $matchResult) {
                    $startLine = $node->getStartLine();
                    $headingLevel = $node->getLevel();
                }
            }
        }

        if (null === $startLine) {
            return null;
        }

        $sectionMarkdown = implode("\n", array_slice($lines, $startLine, $endLine - $startLine));
        $html = (string) $converter->convert($sectionMarkdown);
        return $this->rewriteRelativeLinks($html, $relativePath);
    }

    private function rewriteRelativeLinks(string $html, string $relativePath): string
    {
        return preg_replace_callback(
            '/<a\s+[^>]*href=["\']([^"\']+\.md)["\']/i',
            function (array $matches) use ($relativePath): string {
                $targetRelative = $this->normalizeRelativeLink($relativePath, $matches[1]);

                return sprintf(
                    '<a href="#" data-action="terms-tabs#openTab" data-terms-tabs-path-param="%s"',
                    htmlspecialchars($targetRelative, ENT_QUOTES)
                );
            },
            $html
        );
    }

    private function getConverter(): GithubFlavoredMarkdownConverter
    {
        return new GithubFlavoredMarkdownConverter();
    }

    private function normalizeRelativeLink(string $fromRelativePath, string $linkHref): string
    {
        $fromDir = dirname($fromRelativePath);
        $combined = $fromDir . '/' . $linkHref;

        $parts = [];
        foreach (explode('/', $combined) as $segment) {
            if ($segment === '.' || $segment === '') {
                continue;
            }
            if ($segment === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $segment;
        }

        return implode('/', $parts);
    }
}
