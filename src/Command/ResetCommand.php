<?php

namespace App\Command;

use App\Entity\ServerManager\GameConfigFile;
use App\Entity\ServerManager\GameGeoServer;
use App\Entity\ServerManager\GameList;
use App\Entity\ServerManager\GameSave;
use App\Entity\ServerManager\GameWatchdogServer;
use App\Message\GameList\GameListArchiveMessage;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:reset',
    description: 'Reset your Server Manager. You have options to selectively or completely remove data.',
)]
class ResetCommand extends Command
{

    private SymfonyStyle $io;

    private Filesystem $fileSystem;

    public function __construct(
        private readonly EntityManagerInterface $mspServerManagerEntityManager,
        private readonly KernelInterface $kernel,
        private readonly MessageBusInterface $messageBus,
        private readonly ParameterBagInterface $params
    ) {
        parent::__construct();
        $this->fileSystem = new Filesystem;
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'sessions',
                InputArgument::OPTIONAL,
                'An optional range of sessions to only delete, using a min-max notation, e.g. 1-12 or 4-29.'
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'The command will not ask for confirmation. Makes command non-interactive.'
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
        }
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            'Are you sure you want to continue?',
            false
        );
        // @phpstan-ignore-next-line "Call to an undefined method"
        if (!$input->getOption('force') && !$helper->ask($input, $output, $question)) {
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

    public function selectiveSessionReset(array $sessionRange): void
    {
        if ($this->io->isDebug()) {
            $this->io->info(
                "This is a selective session reset. Only removing selected sessions, nothing else."
            );
        }
        $sessions = $this->mspServerManagerEntityManager->getRepository(GameList::class)
            ->matching(
                (new Criteria())
                    ->where(Criteria::expr()->gte('id', $sessionRange[0]))
                    ->andWhere(Criteria::expr()->lte('id', $sessionRange[1]))
            );
        $this->removeGameLists($sessions);
    }

    public function hardReset(): void
    {
        if ($this->io->isDebug()) {
            $this->io->info(
                "This is a hard reset. Removing sessions, saves, uploaded configs, added geoservers and watchdogs."
            );
        }
        
        $sessions = $this->mspServerManagerEntityManager->getRepository(GameList::class)->findAll();
        $this->removeGameLists($sessions);

        $saves = $this->mspServerManagerEntityManager->getRepository(GameSave::class)->findAll();
        $this->removeGameSaves($saves);
        
        $configs = $this->mspServerManagerEntityManager->getRepository(GameConfigFile::class)->findAll();
        $this->removeGameConfigs($configs);
        
        $geoservers = $this->mspServerManagerEntityManager->getRepository(GameGeoServer::class)
            ->matching((new Criteria())->where(Criteria::expr()->neq('id', 1)));
        $this->removeEntities($geoservers, 'GeoServers');
        
        $watchdogservers = $this->mspServerManagerEntityManager->getRepository(GameWatchdogServer::class)
            ->matching((new Criteria())->where(Criteria::expr()->neq('id', 1)));
        $this->removeEntities($watchdogservers, 'Watchdogs');
    }

    /** @param Collection<int, GameList> $sessions */
    private function removeGameLists($sessions): void
    {
        if (count($sessions) === 0) {
            $this->io->info("No game sessions to remove.");
            return;
        }
        foreach ($sessions as $session) {
            if ($this->io->isDebug()) {
                $this->io->info("Dispatching archive message for session #{$session->getId()}");
            }
            $this->messageBus->dispatch(new GameListArchiveMessage($session->getId()));
        }
        if ($this->io->isDebug()) {
            $this->io->info("Waiting 20 seconds before removing all game sessions from the database...");
        }
        sleep(20);
        foreach ($sessions as $session) {
            if ($this->io->isDebug()) {
                $this->io->info("Removing log and game_list record of session #{$session->getId()}");
            }
            $this->fileSystem->remove(
                $this->kernel->getProjectDir()."/ServerManager/log/log_session_{$session->getId()}.log"
            );
            $this->removeEntity($session);
        }
    }

    /** @param array<int, GameSave> $saves */
    private function removeGameSaves($saves): void
    {
        if (count($saves) === 0) {
            $this->io->info("No game saves to remove.");
            return;
        }
        foreach ($saves as $save) {
            if ($this->io->isDebug()) {
                $this->io->info("Removing save ZIP and game_save record of save #{$save->getId()}");
            }
            $saveFileName = sprintf($this->params->get('app.server_manager_save_name'), $save->getId());
            $saveFilePath = $this->params->get('app.server_manager_save_dir').$saveFileName;
            $this->fileSystem->remove($saveFilePath);
            $this->removeEntity($save);
        }
    }

    /** @param array<int, GameConfigFile> $saves */
    private function removeGameConfigs($configs): void
    {
        foreach ($configs as $config) {
            foreach ($config->getGameConfigVersion() as $configVersion) {
                if ($configVersion->getUploadUser() != 1) {
                    if ($this->io->isDebug()) {
                        $this->io->info("Removing config version #{$configVersion->getId()}");
                    }
                    $filePath = $this->params->get('app.server_manager_config_dir').$configVersion->getFilePath();
                    $this->fileSystem->remove($filePath);
                    $config->removeGameConfigVersion($configVersion);
                    $this->removeEntity($configVersion);
                } else {
                    $this->io->info("Skipping pre-installed config version #{$configVersion->getId()}");
                }
            }
            if (count($config->getGameConfigVersion()) === 0) {
                if ($this->io->isDebug()) {
                    $this->io->info("Removing config file #{$config->getId()}");
                }
                $this->fileSystem->remove($this->params->get('app.server_manager_config_dir').$config->getFilename());
                $this->removeEntity($config);
            }
        }
    }

    /** @param Collection<int, object> $entities */
    private function removeEntities($entities, string $className): void
    {
        if (count($entities) === 0) {
            $this->io->info("No {$className} entities to remove.");
            return;
        }
        foreach ($entities as $entity) {
            if ($this->io->isDebug()) {
                $this->io->info("Removing entity of type ".get_class($entity));
            }
            $this->removeEntity($entity);
        }
    }

    private function removeEntity(object $entity): void
    {
        $this->mspServerManagerEntityManager->remove($entity);
        $this->mspServerManagerEntityManager->flush();
    }
}
