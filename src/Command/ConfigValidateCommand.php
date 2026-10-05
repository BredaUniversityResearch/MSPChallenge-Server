<?php

namespace App\Command;

use App\Domain\Config\ConfigDirectory;
use App\Domain\Config\ConfigLoader;
use App\Domain\Config\InvalidSessionConfigException;
use App\Domain\Config\Merge\RegionConfigMerger;
use App\Domain\Config\SessionConfigValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
    name: 'app:config:validate',
    description: 'Checks configs the way an upload is checked: the final config (merged with its parents) has to '
        . 'match the schema, and a raster layer needs layer_width and layer_height. Exits with status 1 when a '
        . 'config is not valid.'
)]
final class ConfigValidateCommand extends ConfigCommand
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly SessionConfigValidator $validator,
        private readonly string $defaultDir = 'ServerManager/configfiles'
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'files',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Config files to check (stripped or complete). Default: all configs below --dir'
            )
            ->addOption(
                'dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Config root, where the parent configs are (anywhere below it). Absolute, or relative to the '
                . 'project dir',
                $this->defaultDir
            )
            ->addOption(
                'pattern',
                null,
                InputOption::VALUE_REQUIRED,
                'File name pattern of the configs, anywhere below --dir',
                '*.json'
            )
            ->addFormatOption();
    }

    protected function perform(InputInterface $input, ConfigReport $report): int
    {
        $root = Path::makeAbsolute((string)$input->getOption('dir'), $this->projectDir);
        if (!is_dir($root)) {
            return $report->fail("Config directory not found: $root", 'dir_not_found');
        }
        $directory = new ConfigDirectory($root);
        $loader = new ConfigLoader($root, $this->validator);
        $skipped = [];
        try {
            $files = $directory->resolveFiles(
                (array)$input->getArgument('files'),
                getcwd() ?: $this->projectDir,
                (string)$input->getOption('pattern'),
                $skipped
            );
        } catch (\Throwable $e) {
            return $report->failThrowable($e);
        }
        // a file that is not valid JSON may be a config, and is not valid: it is checked like the others
        foreach (array_keys($skipped) as $path) {
            $files[(string)preg_replace('/\.json$/', '', $directory->relativePath($path))] = $path;
        }
        ksort($files);
        if ($files === []) {
            $report->warning('No configs found.', 'no_configs');
            return Command::SUCCESS;
        }

        $results = [];
        foreach ($files as $id => $path) {
            $results[] = $this->check($id, $path, $directory, $loader);
        }
        $invalid = count(array_filter($results, static fn(array $result) => !$result['valid']));
        $report->data = ['results' => $results, 'valid' => count($results) - $invalid, 'invalid' => $invalid];
        foreach ($results as $result) {
            foreach ($result['warnings'] as $warning) {
                $report->warning($warning['message'], $warning['code'], ['file' => $result['id']]);
            }
        }
        if ($invalid > 0) {
            return $report->fail(
                sprintf('%d of %d config(s) are not valid.', $invalid, count($results)),
                'validation_failed',
                details: ['invalid' => $invalid, 'total' => count($results)]
            );
        }
        return Command::SUCCESS;
    }

    /**
     * @return array{id: string, path: string, kind: ?string, valid: bool, errors: list<array<string, mixed>>,
     *     warnings: list<array<string, mixed>>}
     */
    private function check(string $id, string $path, ConfigDirectory $directory, ConfigLoader $loader): array
    {
        $result = [
            'id' => $id,
            'path' => $directory->relativePath($path),
            'kind' => null,
            'valid' => false,
            'errors' => [],
            'warnings' => [],
        ];
        try {
            $contents = (string)@file_get_contents($path);
            $config = $loader->decode($contents); // a syntax error is told with its line and column
            $result['kind'] = RegionConfigMerger::isStripped($config) ? 'stripped' : 'complete';
            $warnings = [];
            $merged = $loader->merge($contents, $warnings);
            foreach ($warnings as $warning) {
                $result['warnings'][] = ['code' => 'merge_warning', 'message' => $warning];
            }
            foreach ($loader->errors($merged) as $message) {
                $result['errors'][] = ['code' => 'invalid_config', 'message' => $message];
            }
            $result['valid'] = $result['errors'] === [];
        } catch (\Throwable $e) {
            $error = ConfigReport::describe($e);
            unset($error['details']['errors']);
            if (($error['details'] ?? []) === []) {
                unset($error['details']);
            }
            // one entry for each thing that is wrong, also when several are found at once
            $messages = $e instanceof InvalidSessionConfigException ? $e->getErrors() : [$error['message']];
            foreach ($messages as $message) {
                $result['errors'][] = ['message' => $message] + $error;
            }
        }
        return $result;
    }
}
