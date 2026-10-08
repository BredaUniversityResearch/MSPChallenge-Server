<?php

namespace App\Domain\Config;

/**
 * Uploading a config together with the parents it needs, in as many steps as it takes.
 *
 * A config that needs no parent is processed at once. If a parent is missing (it is neither among the uploaded files
 * nor on the server), or the config itself, the files are kept (PendingConfigUploads) and nothing is processed until
 * the missing files have been uploaded too. Cancelling throws everything away.
 *
 * What was just uploaded is rejected as a whole, and not kept, when a file is no valid JSON, when the files
 * contradict each other (two configs, parents that loop), or when the complete upload does not give a valid config.
 * The user can upload corrected files then: what was waiting before is still there.
 */
final class ConfigUploads
{
    public function __construct(
        private readonly ConfigLoader $loader,
        private readonly PendingConfigUploads $pending
    ) {
    }

    /**
     * Adds files to the upload that is waiting (if there is one), and processes it when it is complete.
     *
     * @param ?string $token the upload that is waiting, if any
     * @param array<string, string> $files the files that were just uploaded: name => contents. A file with the name of
     *        a file that is waiting replaces it.
     * @throws \JsonException
     * @throws \Random\RandomException
     */
    public function submit(?string $token, array $files): UploadProgress
    {
        $this->pending->purgeExpired();
        $token = $token !== null && $this->pending->exists($token) ? $token : null;

        $errors = [];
        foreach ($files as $name => $contents) {
            try {
                $this->loader->decode($contents);
            } catch (InvalidSessionConfigException $e) {
                $errors[] = $name . ': ' . $e->getErrors()[0];
            }
        }
        if ($errors !== []) {
            return $this->rejected($token, $errors);
        }

        $all = $token === null ? [] : $this->pending->files($token);
        foreach ($files as $name => $contents) {
            $all[(string)$name] = $contents;
        }
        if (count($all) > PendingConfigUploads::MAX_FILES) {
            return $this->rejected($token, [
                sprintf('An upload can have at most %d files.', PendingConfigUploads::MAX_FILES)
            ]);
        }

        $inspection = $this->loader->inspectUpload($all);
        if ($inspection->isInvalid()) {
            return $this->rejected($token, $inspection->errors);
        }
        if ($inspection->isComplete()) {
            $check = $this->loader->checkUploadFiles($all);
            if (!$check->isValid()) {
                return $this->rejected($token, $check->errors);
            }
            if ($token !== null) {
                $this->pending->discard($token);
            }
            return new UploadProgress(UploadProgress::DONE, contents: $check->contents);
        }

        $token ??= $this->pending->create();
        $this->pending->add($token, $files);
        return $this->waiting($token, $inspection, array_keys($all));
    }

    /**
     * The upload that is waiting, or an idle progress when there is none.
     */
    public function describe(?string $token): UploadProgress
    {
        if ($token === null || !$this->pending->exists($token)) {
            return new UploadProgress(UploadProgress::IDLE);
        }
        $files = $this->pending->files($token);
        $inspection = $this->loader->inspectUpload($files);
        return $this->waiting($token, $inspection, array_keys($files));
    }

    /**
     * Throws away the upload that is waiting, with all its files.
     */
    public function cancel(?string $token): void
    {
        if ($token !== null) {
            $this->pending->discard($token);
        }
    }

    /**
     * @param string[] $errors
     */
    private function rejected(?string $token, array $errors): UploadProgress
    {
        $waiting = $this->describe($token);
        return new UploadProgress(
            UploadProgress::REJECTED,
            $waiting->token,
            $waiting->files,
            $errors,
            $waiting->missing,
            $waiting->missingConfig,
            $waiting->unused
        );
    }

    /**
     * @param string[] $files
     */
    private function waiting(string $token, UploadInspection $inspection, array $files): UploadProgress
    {
        return new UploadProgress(
            UploadProgress::WAITING,
            $token,
            array_map('strval', $files),
            [],
            $inspection->missing,
            $inspection->missingConfig,
            $inspection->unused
        );
    }
}
