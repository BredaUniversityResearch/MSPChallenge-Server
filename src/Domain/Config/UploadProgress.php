<?php

namespace App\Domain\Config;

/**
 * Where an upload stands after files were added to it (or when it is looked at).
 */
final readonly class UploadProgress
{
    /** Nothing is waiting. */
    public const string IDLE = 'idle';
    /** The upload is not complete: the files are kept, and the missing files have to be uploaded. */
    public const string WAITING = 'waiting';
    /** What was just uploaded is refused and nothing of it is kept; what was waiting before still is. */
    public const string REJECTED = 'rejected';
    /** The upload is complete and valid: the config is ready to be stored, and nothing is kept. */
    public const string DONE = 'done';

    /**
     * @param ?string $token the upload that is waiting, null when there is none
     * @param string[] $files the names of the files that are waiting
     * @param string[] $errors why the upload was rejected
     * @param array<int, array{file: string, neededBy: string}> $missing
     * @param string[] $unused uploaded generic configs that the config does not need
     * @param ?string $contents the config to store, when the upload is done: complete, in the new shape
     */
    public function __construct(
        public string $state,
        public ?string $token = null,
        public array $files = [],
        public array $errors = [],
        public array $missing = [],
        public bool $missingConfig = false,
        public array $unused = [],
        public ?string $contents = null
    ) {
    }

    public function isDone(): bool
    {
        return $this->state === self::DONE;
    }

    public function isWaiting(): bool
    {
        return $this->state === self::WAITING;
    }

    public function isRejected(): bool
    {
        return $this->state === self::REJECTED;
    }

    /**
     * What the user has to do to complete the upload.
     */
    public function message(): string
    {
        $parts = [];
        if ($this->missingConfig) {
            $parts[] = 'The configuration itself is missing: all the files are generic configs. Add the configuration '
                . 'file that uses them.';
        }
        foreach ($this->missing as $missing) {
            $parts[] = sprintf('Missing: %s, the parent of %s.', $missing['file'], $missing['neededBy']);
        }
        return implode(' ', $parts);
    }
}
