<?php

namespace App\Command;

use App\Entity\ServerManager\GameList;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
        private readonly KernelInterface $kernel
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
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            'This will reset your Server Manager. Are you sure you want to continue?',
            false
        );
        if (!$helper->ask($input, $output, $question)) {
            return Command::SUCCESS;
        }

        $sessionRange = $input->getArgument('sessions');
        if ($sessionRange) {
            $sessionRange = explode('-', $sessionRange);
            if (count($sessionRange) !== 2) {
                throw new \Exception('Please specify a sessions range in the format min-max, like this: 1-10');
            }
        } else {
            $sessionRange[0] = 1;
            $sessionRange[1] = 99;
        }
        for ($id = $sessionRange[0]; $id <= $sessionRange[1]; $id++) {
            $session = $this->mspServerManagerEntityManager->getRepository(GameList::class)->find($id);
            if (is_null($session)) {
                continue;
            }
            $filesystem = new Filesystem();
            $filesystem->remove([
                $this->kernel->getProjectDir()."/ServerManager/log/log_session_{$session->getId()}.log",
                $this->kernel->getProjectDir()."/ServerManager/session_archive/session_archive_{$session->getId()}.zip",
                $this->kernel->getProjectDir()."/session_archive/session_archive_{$session->getId()}.zip",
                $this->kernel->getProjectDir()."/raster/{$session->getId()}/",
                $this->kernel->getProjectDir()."/running_session_config/session_config_{$session->getId()}.json"
            ]);
            // todo: delete session database
            $this->mspServerManagerEntityManager->remove($session);
            $this->mspServerManagerEntityManager->flush();
        }
        
        $io = new SymfonyStyle($input, $output);
        $io->success('Server reset complete.');

        return Command::SUCCESS;
    }
}
