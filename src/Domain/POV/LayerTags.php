<?php

namespace App\Domain\POV;

use ArrayIterator;
use IteratorAggregate;

class LayerTags implements IteratorAggregate
{
    private array $tags = [];

    /**
     * @param string[] $tags
     */
    public function __construct(array $tags)
    {
        $this->tags = $tags;
    }

    public static function createFromFromCommaSeparatedString(string $commaSeparatedString): self
    {
        $tagsArray = explode(',', $commaSeparatedString);
        $tagsArray = array_map('trim', $tagsArray);
        return new self($tagsArray);
    }

    public function addTag(string $tag): self
    {
        $this->tags[] = $tag;
        return $this;
    }

    /**
     * Check if the current instance has the same tags as another LayerTags instance (case-insensitive).
     *
     * @param LayerTags $other
     * @return bool
     */
    public function matches(LayerTags $other): bool
    {
        // Convert tags to lowercase before sorting
        $currentTags = array_map('strtolower', $this->tags);
        $otherTags = array_map('strtolower', $other->tags);
        // Check if all tags in $currentTags are present in $otherTags
        return empty(array_diff($currentTags, $otherTags));
    }

    // Implementing IteratorAggregate interface
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->tags);
    }
}
