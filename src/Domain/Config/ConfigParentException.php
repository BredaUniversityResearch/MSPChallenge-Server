<?php

namespace App\Domain\Config;

/**
 * The parent of a config cannot be used: it does not exist, it is not a generic config, the parents loop, or the name
 * of the parent is ambiguous.
 *
 * Besides the message (for people) an exception has a reason (for programs, see the constants: they are part of what
 * the config tools report) and the details that belong to it, such as the name of the parent that is missing.
 */
final class ConfigParentException extends \RuntimeException
{
    /** A config names a parent that no file has the name of. */
    public const string MISSING = 'parent_missing';
    /** Two files have the name of the parent. */
    public const string AMBIGUOUS = 'parent_ambiguous';
    /** The parents of a config are each other's parents. */
    public const string LOOP = 'parent_loop';
    /** A chain of parents has more levels than is allowed. */
    public const string TOO_DEEP = 'parent_too_deep';
    /** A config that is named as a parent is not a generic config. */
    public const string NOT_GENERIC = 'parent_not_generic';
    /** metadata.parent is not a name of a file without its extension. */
    public const string INVALID_NAME = 'parent_name_invalid';
    /** The file of a parent cannot be read. */
    public const string UNREADABLE = 'parent_unreadable';
    /** A config has layers that refer to a generic layer, but does not say which parent has it. */
    public const string REQUIRED = 'parent_required';

    /**
     * @param string $reason one of the constants of this class
     * @param array<string, mixed> $details for example the name of the parent, and what needs it
     */
    public function __construct(
        string $message,
        public readonly string $reason = self::MISSING,
        public readonly array $details = []
    ) {
        parent::__construct($message);
    }
}
