<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ServerManagerFixtures extends Fixture implements EventSubscriberInterface
{
    private OutputInterface $output;

    public function load(ObjectManager $manager): void
    {
        $dbName = $_ENV['DBNAME_SERVER_MANAGER'] ?? 'msp_server_manager';
        if ($manager->getConnection()->getParams()['dbname'] !== $dbName) {
            $this->output->writeln(
                'Skipping '.__CLASS__ .'. It requires a game session database. '.
                'Please use "--em" to set the game session entity manager. '.
                'This migrations requires a server manager database. Please use --em='.$dbName
            );
            return;
        }
        $manager->flush();
    }

    #[ArrayShape([ConsoleEvents::COMMAND => "string"])] public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'init',
        ];
    }

    public function init(ConsoleCommandEvent $event): void
    {
        $this->output = $event->getOutput();
    }
}
