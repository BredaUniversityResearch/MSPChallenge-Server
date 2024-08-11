<?php

namespace App\DataFixtures;

use App\Entity\ServerManager\Setting;
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
        $databaseName = ($_ENV['APP_ENV'] != 'test') ?
            $_ENV['DBNAME_SERVER_MANAGER'] :
            $_ENV['DBNAME_SERVER_MANAGER'].'_test';
        $defaultDatabaseName = ($_ENV['APP_ENV'] != 'test') ? 'msp_server_manager' : 'msp_server_manager_test';
        $dbName = $databaseName ?? $defaultDatabaseName;
        if ($manager->getConnection()->getParams()['dbname'] !== $dbName) {
            $this->output->writeln(
                'Skipping '.__CLASS__ .' for connection '.$manager->getConnection()->getParams()['dbname'].
                '. Please use "--em" to set the game session entity manager. '.
                'This migrations requires a server manager database. Please use --em='.$dbName
            );
            return;
        }
        $setting = new Setting();
        $setting->setName('server_id');
        $setting->setValue('643fb212a63121.89114231');
        $manager->persist($setting);

        $setting2 = new Setting();
        $setting2->setName('server_password');
        $setting2->setValue('1681895954');
        $manager->persist($setting2);

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