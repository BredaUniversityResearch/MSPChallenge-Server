<?php

namespace App\Tests\ServerManager;

use App\Entity\ServerManager\GameConfigVersion;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Entity\ServerManager\GameList;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class SessionCreationTest extends KernelTestCase
{
    public function testGameSessionCreation(): void
    {
        $kernel = self::bootKernel();
        $this->assertSame('test', $kernel->getEnvironment());

        $em = static::getContainer()->get('doctrine')->getManager('msp_server_manager');

        $newGameSession = new GameList();
        $newGameSession->setName('testSession');
        $newGameSession->setGameConfigVersion(
            $em->getRepository(GameConfigVersion::class)->findOneBy(['id' => 1]) // North Sea config
        );
        $newGameSession->setPasswordAdmin('test');
        $em->persist($newGameSession);
        $em->flush();

        $message = static::getContainer()->get('messenger.bus.default');
        $message->dispatch((new GameList($newGameSession->getId())));

        $transport = static::getContainer()->get('messenger.transport.async_test');
        $this->assertCount(
            1,
            $transport->get(),
            'In-memory transport does not contain the message to start session creation.'
        ); // will only work on in_memory transports

        $app = new Application(static::bootKernel());
        $input = new ArrayInput([
            'command' => 'messenger:consume',
            'receivers' => ['async'],
            '--env' => 'test',
            '--limit' => 1,
        ]);
        $input->setInteractive(false);
        $app->doRun($input, new NullOutput());

        $gameList = $em->getRepository(GameList::class);
        $count = (int) $gameList
            ->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();
        $this->assertSame(1, $count, 'Amount of game sessions is not the same');

        $gameSession = $gameList->findOneBy(['id' => '1']);
        $this->assertSame('testSession', $gameSession->getName());
        $this->assertSame('healthy', (string) $gameSession->getSessionState());
        $this->assertFileExists(
            $kernel->getLogDir().'/log_session_'.$gameSession->getId().'.log',
            'Log file does not exist, suggesting SessionCreationHandler was unsuccessful.'
        );
    }

    public static function setUpBeforeClass(): void
    {
        // completely removes, creates and migrates the test database
        $app = new Application(static::bootKernel());

        $input1 = new ArrayInput([
            'command' => 'doctrine:database:drop',
            '--force' => true,
            '--no-interaction' => true,
        ]);
        $input1->setInteractive(false);
        $app->doRun($input1, new NullOutput());

        $input2 = new ArrayInput([
            'command' => 'doctrine:database:create',
        ]);
        $input2->setInteractive(false);
        $app->doRun($input2, new NullOutput());

        $input3 = new ArrayInput([
            'command' => 'doctrine:migrations:migrate',
            '--em' => 'msp_server_manager'
        ]);
        $input3->setInteractive(false);
        $app->doRun($input3, new NullOutput());

        $input4 = new ArrayInput([
            'command' => 'doctrine:fixtures:load',
            '--em' => 'msp_server_manager',
            '--append' => true
        ]);
        $input4->setInteractive(false);
        $app->doRun($input4, new NullOutput());
    }
}
