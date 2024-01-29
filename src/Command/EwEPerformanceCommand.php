<?php

namespace App\Command;

use App\Domain\Services\ConnectionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:eweperformance',
    description: 'Ascertains the performance of each MEL-EwE month simulated, and returns key statistics.',
)]
class EwEPerformanceCommand extends Command
{

    private const MWS_LOG_PATH = '/var/log/supervisor/msw.log';

    private SymfonyStyle $io;

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'raw',
                null,
                null,
                'Show all month performance log lines, being the raw data on which the stats are based.'
            );
        $this
            ->addOption(
                'flush',
                null,
                null,
                'Flushes the log file, so you can then create a single new session and do the benchmarking properly.'
            );
        $this
            ->addArgument(
                'split',
                InputArgument::IS_ARRAY,
                'Month identifiers by which to split the stats.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $fileSystem = new Filesystem();
        if (!$fileSystem->exists(self::MWS_LOG_PATH)) {
            $this->io->error('Cannot find msw.log file, so cannot continue.');
            return Command::FAILURE;
        }
        if ($input->getOption('flush')) {
            $fileSystem->dumpFile(self::MWS_LOG_PATH, '');
            $this->io->info('Flushed the msw.log file.'.
                'Now you can create a single new session, run its simulations, and then run this command again '.
                'to get reliable performance stats.');
            return Command::SUCCESS;
        }

        $split = $input->getArgument('split');
        if (!empty($split)) {
            $this->io->info("Splitting at month(s) ".implode(',', $split));
        }
        $mswContents = file_get_contents(self::MWS_LOG_PATH);
        $monthPerformanceLines = preg_match_all('/^Month (-?\d+) executed in: (\d+)ms$/m', $mswContents, $matches);
        if ($input->getOption('raw')) {
            $this->io->info("Found {$monthPerformanceLines} lines in msw.log indicating EwE month run performance:");
            $this->io->listing($matches[0]);
        }
        if (str_contains($matches[0][0] ?? '', 'Month -1 executed')) {
            $this->io->info('Skipping month -1 in stat calculation, as this month includes EwE startup.');
            unset($matches[0][0]);
            unset($matches[1][0]);
            unset($matches[2][0]);
        }
        $splitted = 0;
        $average[$splitted] = 0;
        $total[$splitted] = 0;
        foreach ($matches[2] as $key => $milliSeconds) {
            $average[$splitted] += (int)$milliSeconds;
            $total[$splitted]++;
            if (in_array($matches[1][$key], $split)) {
                $splitted++;
                $average[$splitted] = 0;
                $total[$splitted] = 0;
            }
        }
        foreach ($average as $key => $splitAdded) {
            if (!isset($split[$key])) {
                $split[$key] = 'last';
            }
            if (!isset($split[$key - 1])) {
                $startMonth = 0;
            } else {
                $startMonth = $split[$key -1] + 1;
            }
            if ($total[$key] > 0) {
                $splitAverage = round($splitAdded / $total[$key]);
                $returnList[] = "Average performance per month {$startMonth}-{$split[$key]}: " .
                    "{$splitAverage} milliseconds";
            }
        }
        $this->io->listing($returnList);
        $this->io->info('Done!');
        return Command::SUCCESS;
    }
}
