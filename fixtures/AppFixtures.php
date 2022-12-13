<?php

namespace App\DataFixtures;

use App\Domain\Helper\Util;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AppFixtures extends Fixture implements EventSubscriberInterface
{
    private OutputInterface $output;

    public function load(ObjectManager $manager): void
    {
        $prefix = $_ENV['DBNAME_SESSION_PREFIX'] ?? 'msp_session_';
        if (!Util::hasPrefix($manager->getConnection()->getParams()['dbname'], $prefix)) {
            $this->output->writeln(
                'Skipping '.__CLASS__.'. It requires a game session database. ' .
                'Please use "--em" to set the game session entity manager. E.g. --em='.$prefix.'1'
            );
            return;
        }
        // $product = new Product();
        // $manager->persist($product);

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
