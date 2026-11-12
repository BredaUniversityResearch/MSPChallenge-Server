<?php

namespace App\Command;

use App\Domain\Config\ConfigDirectory;
use App\Domain\Config\ConfigParentException;
use App\Domain\Config\ConfigParents;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
    name: 'app:config:list',
    description: 'Lists the configs and the generic configs (parents) below a config root: what every config is, '
        . 'which parents it has, which configs use a parent, and what is wrong (a parent that is missing, names that '
        . 'two files have, files that are not valid JSON).'
)]
final class ConfigListCommand extends ConfigCommand
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly string $defaultDir = 'ServerManager/configfiles'
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Config root (absolute, or relative to the project dir). The configs and the generic configs can be '
                . 'anywhere below it',
                $this->defaultDir
            )
            ->addOption(
                'pattern',
                null,
                InputOption::VALUE_REQUIRED,
                'File name pattern of the files to look at, anywhere below --dir',
                '*.json'
            )
            ->addOption(
                'check',
                null,
                InputOption::VALUE_NONE,
                'Exit with status 1 when something is wrong (for CI): a config or parent with a problem, a name that '
                . 'two files have, or a file that is not valid JSON'
            )
            ->addExcludeOption()
            ->addFormatOption();
    }

    protected function perform(InputInterface $input, ConfigReport $report): int
    {
        $root = Path::makeAbsolute((string)$input->getOption('dir'), $this->projectDir);
        if (!is_dir($root)) {
            return $report->fail("Config directory not found: $root", 'dir_not_found');
        }
        $directory = new ConfigDirectory($root, $this->excludesOf($input));
        $scan = $directory->scan((string)$input->getOption('pattern'));
        $parents = ConfigParents::fromDirectory($directory);

        // the chain of every config and every generic config, and what is wrong with it
        $configs = [];
        foreach ($scan['configs'] as $id => $config) {
            [$chain, $problem] = $this->chain($id, $config, $parents);
            $configs[] = [
                'id' => $id,
                'path' => $directory->relativePath($config['path']),
                'kind' => $config['stripped'] ? 'stripped' : 'complete',
                'parent' => $config['parent'],
                'chain' => $chain,
                'layers' => $config['layers'],
                'problem' => $problem,
            ];
        }
        $generics = [];
        foreach ($scan['generics'] as $id => $generic) {
            [, $problem] = $this->chain($id, $generic, $parents);
            $children = [];
            foreach ([$scan['configs'], $scan['generics']] as $group) {
                foreach ($group as $childId => $child) {
                    if ($child['parent'] === $generic['name']) {
                        $children[] = $childId;
                    }
                }
            }
            $generics[] = [
                'name' => $generic['name'],
                'id' => $id,
                'path' => $directory->relativePath($generic['path']),
                'parent' => $generic['parent'],
                'layers' => $generic['layers'],
                'children' => $children,
                'problem' => $problem,
            ];
        }

        // names that are the name of a parent, and that two files have: nobody can tell which one is meant
        $names = [];
        foreach ($scan['generics'] as $generic) {
            $names[$generic['name']] = true;
        }
        foreach ([$scan['configs'], $scan['generics']] as $group) {
            foreach ($group as $entry) {
                if ($entry['parent'] !== null) {
                    $names[$entry['parent']] = true;
                }
            }
        }
        $duplicates = [];
        foreach ($directory->jsonFileNames() as $name => $paths) {
            if (isset($names[$name]) && count($paths) > 1) {
                sort($paths);
                $duplicates[] = [
                    'name' => $name,
                    'paths' => array_map(fn(string $path) => $directory->relativePath($path), $paths),
                ];
            }
        }
        $skipped = [];
        foreach ($scan['skipped'] as $path => $why) {
            $skipped[] = ['path' => $directory->relativePath($path), 'reason' => $why];
        }

        $problems = count(array_filter($configs, static fn(array $c) => $c['problem'] !== null))
            + count(array_filter($generics, static fn(array $g) => $g['problem'] !== null));
        $report->data = [
            'root' => $root,
            'configs' => $configs,
            'parents' => $generics,
            'duplicates' => $duplicates,
            'skipped' => $skipped,
            'summary' => [
                'configs' => count($configs),
                'parents' => count($generics),
                'problems' => $problems,
                'duplicates' => count($duplicates),
                'skipped' => count($skipped),
            ],
        ];

        $wrong = $problems + count($duplicates) + count($skipped);
        if ($input->getOption('check') && $wrong > 0) {
            return $report->finding(
                sprintf('%d thing(s) are wrong: see above.', $wrong),
                'check_failed',
                ['problems' => $problems, 'duplicates' => count($duplicates), 'skipped' => count($skipped)]
            );
        }
        return Command::SUCCESS;
    }

    /**
     * The parents of a config or generic config, nearest parent first, and what is wrong with them.
     *
     * @param array{parent: ?string, parentError: ?array<string, string>} $entry
     * @return array{0: list<string>, 1: ?array<string, mixed>} the names of the parents, and the problem (or null)
     */
    private function chain(string $id, array $entry, ConfigParents $parents): array
    {
        if ($entry['parentError'] !== null) {
            return [[], $entry['parentError']];
        }
        if ($entry['parent'] === null) {
            return [[], null];
        }
        try {
            return [array_reverse(array_keys($parents->chain($entry['parent'], '"' . $id . '"'))), null];
        } catch (ConfigParentException $e) {
            $problem = ['code' => $e->reason, 'message' => $e->getMessage()];
            return [[], $problem + ($e->details === [] ? [] : ['details' => $e->details])];
        }
    }
}
