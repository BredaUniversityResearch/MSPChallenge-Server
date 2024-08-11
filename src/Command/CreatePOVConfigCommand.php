<?php

namespace App\Command;

use App\Domain\POV\ConfigCreator;
use App\Domain\POV\LayerTags;
use App\Domain\POV\Region;
use App\Domain\Services\SymfonyToLegacyHelper;
use Exception;
use Monolog\Logger;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-pov-config',
    description: 'Create a POV config json file for a session by region. Coordinate system: https://epsg.io/3035.',
)]
class CreatePOVConfigCommand extends Command
{
    const OPT_OUTPUT_DIR = 'output-dir';
    const OPT_OUTPUT_JSON_FILENAME = 'output-json-filename';
    const OPT_OUTPUT_PACKAGE_FILENAME = 'output-package-filename';
    const OPT_ZIP = 'compress';
    const OPT_OUTPUT_IMAGE_FORMAT = 'output-image-format';
    const OPT_EXCL_LAYERS_BY_TAGS = 'exclude-layers-by-tags';

    const ARG_SESSION_ID = 'session-id';
    const ARG_REGION_COORDINATES = 'region-coordinates';

    const ARG_REGION_COORDINATES_EXAMPLE = '4048536 3470232 4063746 3488599';
    const REGION_COORDINATES_DESCRIPTION = '4 floats representing the coordinates of the region. In order:' .
        ' region bottom left coordinates x, -y, region top right coordinates x, -y. ' . PHP_EOL .
        'Eg: ' . self::ARG_REGION_COORDINATES_EXAMPLE;

    public function __construct(
        private readonly string $projectDir,
        // below is required by legacy to be auto-wire, has its own ::getInstance()
        SymfonyToLegacyHelper $symfonyToLegacyHelper
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $coordinates = array_map(
            fn($s) => (float)$s,
            explode(' ', self::ARG_REGION_COORDINATES_EXAMPLE)
        );
        $exampleRegion = new Region(...$coordinates);
        $this
            ->addOption(
                self::OPT_OUTPUT_DIR,
                'd',
                InputOption::VALUE_REQUIRED,
                'The path to output directory. Default is: ' .
                    ConfigCreator::getDefaultOutputBaseDir($this->projectDir)
            )
            ->addOption(
                self::OPT_OUTPUT_JSON_FILENAME,
                'j',
                InputOption::VALUE_REQUIRED,
                'The filename of output json file. Default is: ' . ConfigCreator::DEFAULT_CONFIG_FILENAME,
                ConfigCreator::DEFAULT_CONFIG_FILENAME
            )
            ->addOption(
                self::OPT_OUTPUT_PACKAGE_FILENAME,
                'p',
                InputOption::VALUE_REQUIRED,
                'The filename of compressed package file if compression is enabled. ' . PHP_EOL .
                    'Default is based on the region coordinates like: ' .
                    ConfigCreator::getDefaultCompressedFilename($exampleRegion)
            )
            ->addOption(
                self::OPT_OUTPUT_IMAGE_FORMAT,
                'i',
                InputOption::VALUE_REQUIRED,
                'The output image format to use for all extracted images. Default is: ' .
                    ConfigCreator::DEFAULT_IMAGE_FORMAT .
                    '. Current supported formats: ' . implode(', ', \Imagick::queryformats('PNG*')) . PHP_EOL .
                    'For more info, go to https://imagemagick.org/script/formats.php#supported',
            )
            ->addOption(
                self::OPT_ZIP,
                'z',
                InputOption::VALUE_NONE,
                'Enables zip compression of the output to a package file.'
            )
            ->addOption(
                self::OPT_EXCL_LAYERS_BY_TAGS,
                't',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Exclude layers by tags (comma-separated). The tag comparison is case-insensitive.'
            )
            ->addArgument(
                self::ARG_SESSION_ID,
                InputArgument::REQUIRED,
                'The ID of the game session'
            )
            ->addArgument(
                self::ARG_REGION_COORDINATES,
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                self::REGION_COORDINATES_DESCRIPTION
            );
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sessionId = $input->getArgument(self::ARG_SESSION_ID);
        if (!ctype_digit($sessionId)) {
            $io->error('Session ID must be an integer.');
            return Command::FAILURE;
        }
        /** @var array $coordinates */
        $coordinates = $input->getArgument(self::ARG_REGION_COORDINATES);
        if (count($coordinates) !== 4) {
            $io->error('Invalid number of region coordinates. Required: ' . self::REGION_COORDINATES_DESCRIPTION);
            return Command::FAILURE;
        }
        $outputDir = $input->getOption(self::OPT_OUTPUT_DIR);
        $outputJsonFilename = $input->getOption(self::OPT_OUTPUT_JSON_FILENAME);
        $logger = new Logger('pov-config-creator');
        $logger->pushHandler(new ConsoleHandler($output));
        $configCreator = new ConfigCreator($this->projectDir, $sessionId, $logger);
        $region = new Region(...$coordinates);
        try {
            $configCreator
                ->setOutputImageFormat(
                    $input->getOption(self::OPT_OUTPUT_IMAGE_FORMAT) ?: ConfigCreator::DEFAULT_IMAGE_FORMAT
                )
                ->setExcludedLayersByTags(
                    array_map(
                        fn($s) => LayerTags::createFromFromCommaSeparatedString($s),
                        $input->getOption(self::OPT_EXCL_LAYERS_BY_TAGS)
                    )
                );
            $io->success('Using output image format: ' . $configCreator->getOutputImageFormat());
            if ($input->getOption('compress')) {
                $zipPath = $configCreator->createAndZip($region, $outputDir, $outputJsonFilename);
                $io->success('Created config package: ' . $zipPath);
            } else {
                $outputDir = $configCreator->create($region, $outputDir, $outputJsonFilename);
                $io->success('Created config in directory: ' . realpath($outputDir));
            }
        } catch (Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }
}
