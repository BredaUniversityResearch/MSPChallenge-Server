<?php
namespace App\Tests\ServerManager;

use App\Entity\ServerManager\GameConfigVersion;
use App\Entity\ServerManager\GameList;
use App\Message\GameList\GameListCreationMessage;
use App\MessageHandler\GameList\GameListCreationMessageHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class GameListCreationTest extends KernelTestCase
{

    private EntityManagerInterface $emServerManager;

    public function testGameListCreation(): void
    {
        $this->start();
        $newGameSession = new GameList();
        $newGameSession->setName('testSession');
        $newGameSession->setGameConfigVersion(
            $this->emServerManager->getRepository(GameConfigVersion::class)->findOneBy(['id' => 1]) // North Sea config
        );
        $newGameSession->setPasswordAdmin('test');
        $this->emServerManager->persist($newGameSession);
        $this->emServerManager->flush();
    }

    private function start(): void
    {
        $container = static::getContainer();
        $this->emServerManager = $container->get("doctrine.orm.msp_server_manager_entity_manager");
    }

    public function testGameListCreationMessageHandler(): void
    {
        $container = static::getContainer();
        $handler = $container->get(GameListCreationMessageHandler::class);
        $handler->__invoke(new GameListCreationMessage(1));
    }

    public static function setUpBeforeClass(): void
    {
        // completely removes, creates and migrates the test database

        $app = new Application(static::bootKernel());
        $input = new ArrayInput([
            'command' => 'doctrine:database:drop',
            '--connection' => $_ENV['DBNAME_SERVER_MANAGER'],
            '--force' => true,
            '--no-interaction' => true,
        ]);
        $input->setInteractive(false);
        $app->doRun($input, new NullOutput());

        $input2 = new ArrayInput([
            'command' => 'doctrine:database:create',
            '--connection' => $_ENV['DBNAME_SERVER_MANAGER']
        ]);
        $input2->setInteractive(false);
        $app->doRun($input2, new NullOutput());

        $input3 = new ArrayInput([
            'command' => 'doctrine:migrations:migrate',
            '--em' => $_ENV['DBNAME_SERVER_MANAGER'],
        ]);
        $input3->setInteractive(false);
        $app->doRun($input3, new NullOutput());

        $input4 = new ArrayInput([
            'command' => 'doctrine:fixtures:load',
            '--em' => $_ENV['DBNAME_SERVER_MANAGER'],
            '--append' => true
        ]);
        $input4->setInteractive(false);
        $app->doRun($input4, new NullOutput());
    }
}