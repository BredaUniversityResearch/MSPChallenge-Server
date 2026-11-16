<?php

namespace App\Domain\Config;

/**
 * What is known about a set of uploaded files: is it complete (the config and every parent it needs), what is
 * missing, or is something wrong that no further file can repair.
 */
final readonly class UploadInspection
{
    public const string COMPLETE = 'complete';
    public const string INCOMPLETE = 'incomplete';
    public const string INVALID = 'invalid';

    /**
     * @param ?string $config the name of the uploaded file that is the config, null when there is none
     * @param string[] $parents the uploaded files that the config needs, nearest parent first
     * @param array<int, array{file: string, neededBy: string}> $missing files that have to be uploaded: the parent
     *        that is not there, and the file that needs it
     * @param string[] $serverParents the parents that are not uploaded but that the server has
     * @param string[] $unused uploaded generic configs that the config does not need
     * @param string[] $errors what is wrong, when the state is INVALID
     */
    public function __construct(
        public string $state,
        public ?string $config = null,
        public array $parents = [],
        public array $missing = [],
        public bool $missingConfig = false,
        public array $serverParents = [],
        public array $unused = [],
        public array $errors = []
    ) {
    }

    public function isComplete(): bool
    {
        return $this->state === self::COMPLETE;
    }

    public function isIncomplete(): bool
    {
        return $this->state === self::INCOMPLETE;
    }

    public function isInvalid(): bool
    {
        return $this->state === self::INVALID;
    }

    /**
     * What a user has to do next, in a sentence or two.
     */
    public function summary(): string
    {
        if ($this->isInvalid()) {
            return implode(' ', $this->errors);
        }
        $parts = [];
        if ($this->missingConfig) {
            $parts[] = 'The configuration itself is missing: all the files are generic configs. Add the configuration '
                . 'file that uses them.';
        }
        foreach ($this->missing as $missing) {
            $parts[] = sprintf('Missing: %s, the parent of %s.', $missing['file'], $missing['neededBy']);
        }
        return $parts === [] ? 'All the files are there.' : implode(' ', $parts);
    }
}
