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
    description: 'Add a short description for your command',
)]
class ResetCommand extends Command
{


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
                'A range of sessions to delete, using a min-max notation, e.g. 1-12 or 4-29.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sessionRange[0] = 1;
        $sessionRange[1] = 99;
        $sessionRangeInput = $input->getArgument('sessions');
        if ($sessionRangeInput) {
            $sessionRange = explode('-', $sessionRangeInput);
            if (count($sessionRange) !== 2) {
                throw new \Exception('Please specify a sessions range in the format min-max, like this: 1-10');
            }
            $io->info("Only removing sessions {$sessionRange[0]} through {$sessionRange[1]}.");
        }
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            'This will reset your Server Manager. Are you sure you want to continue?',
            false
        );
        // @phpstan-ignore-next-line "Call to an undefined method"
        if (!$helper->ask($input, $output, $question)) {
            return Command::SUCCESS;
        }

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
                $this->kernel->getProjectDir()."/raster/{$session->getId()}/",
                $this->kernel->getProjectDir()."/running_session_config/session_config_{$session->getId()}.json"
            ];
            $io->info('Removing the following files: '.var_export($fileArray, true));
            $filesystem->remove($fileArray);

            $this->dropDatabase($session->getId());

            $this->mspServerManagerEntityManager->remove($session);
            $this->mspServerManagerEntityManager->flush();
        }


        $io->success('Server reset complete.');

        return Command::SUCCESS;
    }

    public function dropDatabase($id): void
    {
        $conn = $this->connectionManager->getGameSessionDbName($id);
        $app = new Application($this->kernel);
        $input = new ArrayInput([
            'command' => 'doctrine:database:drop',
            '--force' => true,
            '--connection' => $conn,
            '--env' => $_ENV['APP_ENV']
        ]);
        $input->setInteractive(false);
        $app->doRun($input, new NullOutput());
    }
}
