<?php

namespace App\Domain\Config;

/**
 * Looks at the files of an upload: a config, and the generic configs (its parents) that come with it.
 *
 * Of the uploaded files, the generic ones (layers with an msp_config_generic_name and no layer_name) are parents, and
 * all the others are configs: there has to be exactly one. A parent is found by its name, which is the name of the
 * file without ".json", first among the uploaded files and then among the generic configs of the server. So an upload
 * only needs the parents that the server does not have, and the parents are never stored on the server.
 *
 * When a parent is missing the upload is incomplete: nothing can be done with it until that file is uploaded too.
 */
final class UploadInspector
{
    /**
     * @param \Closure(string): \stdClass $decode decodes JSON, throws an InvalidSessionConfigException when it
     *        is not valid
     * @param \Closure(string): ?\stdClass $serverParent the generic config <name>.json of the server, null when
     *        it has none
     */
    public function __construct(private readonly \Closure $decode, private readonly \Closure $serverParent)
    {
    }

    /**
     * @param array<string, string> $files the uploaded files: name => contents
     */
    public function inspect(array $files): UploadInspection
    {
        $docs = [];
        $errors = [];
        foreach ($files as $file => $contents) {
            try {
                $docs[(string)$file] = ($this->decode)($contents);
            } catch (InvalidSessionConfigException $e) {
                $errors[] = $file . ': ' . $e->getErrors()[0];
            }
        }
        if ($errors !== []) {
            return new UploadInspection(UploadInspection::INVALID, errors: $errors);
        }

        $generics = []; // the name a parent is looked up by => file
        $unusable = []; // generic configs of which the file name cannot be the name of a parent
        $configs = [];
        foreach ($docs as $file => $doc) {
            $file = (string)$file;
            if (!ConfigParents::isGeneric($doc)) {
                $configs[$file] = $doc;
            } elseif (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'json') {
                $generics[pathinfo($file, PATHINFO_FILENAME)] = $file;
            } else {
                $unusable[] = $file;
            }
        }
        if (count($configs) > 1) {
            return new UploadInspection(UploadInspection::INVALID, errors: [sprintf(
                'Upload one configuration at a time, together with its parents. '
                . 'These files are all configurations: %s.',
                implode(', ', array_keys($configs))
            )]);
        }
        if ($configs === []) {
            return new UploadInspection(
                UploadInspection::INCOMPLETE,
                missingConfig: true,
                unused: array_merge(array_values($generics), $unusable)
            );
        }

        $configFile = (string)array_key_first($configs);
        try {
            return $this->followParents($configFile, $configs[$configFile], $docs, $generics, $unusable);
        } catch (ConfigParentException $e) {
            return new UploadInspection(UploadInspection::INVALID, errors: [$e->getMessage()]);
        }
    }

    /**
     * @param array<string, \stdClass> $docs
     * @param array<string, string> $generics
     * @param string[] $unusable
     * @throws ConfigParentException
     */
    private function followParents(
        string $configFile,
        \stdClass $config,
        array $docs,
        array $generics,
        array $unusable
    ): UploadInspection {
        $parentName = ConfigParents::parentOf($config);
        if ($parentName === null && ConfigParents::needsParent($config)) {
            throw new ConfigParentException(
                sprintf(
                    '%s has layers that refer to a generic layer (msp_config_generic_name), but no metadata.parent '
                    . 'that says which generic config has them.',
                    $configFile
                ),
                ConfigParentException::REQUIRED
            );
        }
        $parents = [];
        $serverParents = [];
        $missing = [];
        $seen = [];
        $neededBy = $configFile;
        while ($parentName !== null) {
            if (isset($seen[$parentName])) {
                throw new ConfigParentException(
                    sprintf(
                        'The parents of %s loop: %s',
                        $configFile,
                        implode(' -> ', array_merge(array_keys($seen), [$parentName]))
                    ),
                    ConfigParentException::LOOP,
                    ['chain' => array_merge(array_keys($seen), [$parentName])]
                );
            }
            if (count($seen) >= ConfigParents::MAX_DEPTH) {
                throw new ConfigParentException(
                    sprintf('%s has more than %d levels of parents.', $configFile, ConfigParents::MAX_DEPTH),
                    ConfigParentException::TOO_DEEP,
                    ['maxDepth' => ConfigParents::MAX_DEPTH]
                );
            }
            $seen[$parentName] = true;
            if (isset($generics[$parentName])) {
                $parents[] = $generics[$parentName];
                $parent = $docs[$generics[$parentName]];
            } else {
                $parent = ($this->serverParent)($parentName);
                if ($parent === null) {
                    $missing[] = ['file' => $parentName . '.json', 'neededBy' => $neededBy];
                    break;
                }
                $serverParents[] = $parentName;
            }
            $neededBy = $parentName . '.json';
            $parentName = ConfigParents::parentOf($parent);
        }
        return new UploadInspection(
            $missing === [] ? UploadInspection::COMPLETE : UploadInspection::INCOMPLETE,
            config: $configFile,
            parents: $parents,
            missing: $missing,
            serverParents: $serverParents,
            unused: array_merge(array_values(array_diff($generics, $parents)), $unusable)
        );
    }
}
