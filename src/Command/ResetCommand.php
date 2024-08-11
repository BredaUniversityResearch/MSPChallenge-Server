<?php

namespace App\Command;

use App\Domain\Services\ConnectionManager;
use App\Entity\ServerManager\GameList;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:reset',
    description: 'Reset your Server Manager. You have options to selectively or completely remove data.',
)]
class ResetCommand extends Command
{

    private SymfonyStyle $io;

    public function __construct(
        private readonly EntityManagerInterface $mspServerManagerEntityManager,
        private readonly KernelInterface $kernel,
        private readonly ConnectionManager $connectionManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'sessions',
                InputArgument::OPTIONAL,
                'An optional range of sessions to only delete, using a min-max notation, e.g. 1-12 or 4-29.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $sessionRangeInput = $input->getArgument('sessions');
        if ($sessionRangeInput) {
            $sessionRange = explode('-', $sessionRangeInput);
            if (count($sessionRange) !== 2) {
                throw new \Exception('Please specify a sessions range in the format min-max, like this: 1-10');
            }
            $this->io->info("Only removing sessions {$sessionRange[0]} through {$sessionRange[1]}, nothing more.");
        } else {
            $this->io->info("This would be a hard reset. The server manager database will be completely reinstalled.");
        }
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            'Are you sure you want to continue?',
            false
        );
        // @phpstan-ignore-next-line "Call to an undefined method"
        if (!$helper->ask($input, $output, $question)) {
            return Command::SUCCESS;
        }

        if ($sessionRangeInput) {
            $this->selectiveSessionReset($sessionRange);
        } else {
            $this->hardReset();
        }

        $this->io->success('Server reset complete.');

        return Command::SUCCESS;
    }

    public function dropDatabase($connection): void
    {
        $app = new Application($this->kernel);
        $input = new ArrayInput([
            'command' => 'doctrine:database:drop',
            '--force' => true,
            '--connection' => $connection,
            '--env' => $_ENV['APP_ENV']
        ]);
        $input->setInteractive(false);
        $app->doRun($input, new NullOutput());
    }

    public function selectiveSessionReset(array $sessionRange): void
    {
        for ($id = $sessionRange[0]; $id <= $sessionRange[1]; $id++) {
            $session = $this->mspServerManagerEntityManager->getRepository(GameList::class)->find($id);
            if (is_null($session)) {
                continue;
            }
            $filesystem = new Filesystem();
            $fileArray = [
                $this->kernel->getProjectDir()."/ServerManager/log/log_session_{$session->getId()}.log",
                $this->kernel->getProjectDir()."/ServerManager/session_archive/session_archive_{$session->getId()}.zip",
                $this->kernel->getProjectDir()."/session_archive/session_archive_{$session->getId()}.zip",
                $this->kernel->getProjectDir()."/raster/{$session->getId()}/archive/",
                $this->kernel->getProjectDir()."/raster/{$session->getId()}/",
                $this->kernel->getProjectDir()."/running_session_config/session_config_{$session->getId()}.json"
            ];
            if ($this->io->isDebug()) {
                $this->io->info('Removing the following files: '.var_export($fileArray, true));
            }
            $filesystem->remove($fileArray);
            $connection = $this->connectionManager->getGameSessionDbName($session->getId());
            if ($this->io->isDebug()) {
                $this->io->info("Removing {$connection} database");
            }
            $this->dropDatabase($connection);
            if ($this->io->isDebug()) {
                $this->io->info("Removing game_list record {$session->getId()} from the msp_server_manager database");
            }
            $this->mspServerManagerEntityManager->remove($session);
            $this->mspServerManagerEntityManager->flush();
            if ($this->io->isDebug()) {
                $this->io->info("========================================");
            }
        }
    }

    public function hardReset(): void
    {
        $this->io->info("This feature has not been implemented yet. Please add a sessions range for now (see --help).");
        //get a list of msp_session_% databases
        //remove all of them
        //empty directories
        //reinstall msp_server_manager database
    }
}
