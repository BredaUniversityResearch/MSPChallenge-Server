<?php

namespace App\Domain\Config\Merge;

/**
 * What stripping one config file would do. Nothing has been written yet.
 */
final readonly class StripPlan
{
    public const string CHANGED = 'changed';
    public const string UNCHANGED = 'unchanged';
    public const string REFUSED = 'refused';

    /**
     * @param self::CHANGED|self::UNCHANGED|self::REFUSED $status CHANGED (the stripped version differs from the
     *        file), UNCHANGED (already stripped) or REFUSED (cannot be stripped without changing its meaning, the
     *        file stays as it is)
     * @param \stdClass $current the file as it is now
     * @param ?\stdClass $stripped the document to write (null when REFUSED)
     * @param string[] $reasons why it was refused
     * @param string[] $warnings
     * @param array<string, int> $stats
     * @param ?\stdClass $effective the final config the stripped version has to give back (null: the file merged
     *        with generic.json)
     */
    public function __construct(
        public string $id,
        public string $path,
        public string $status,
        public \stdClass $current,
        public ?\stdClass $stripped,
        public array $reasons = [],
        public array $warnings = [],
        public array $stats = [],
        public ?\stdClass $effective = null
    ) {
    }
}
