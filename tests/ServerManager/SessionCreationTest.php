<?php

namespace App\Tests\ServerManager;

use App\Entity\ServerManager\GameConfigVersion;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Entity\ServerManager\GameList;

class SessionCreationTest extends KernelTestCase
{
    public function testSomething(): void
    {
        $kernel = self::bootKernel();
        $this->assertSame('test', $kernel->getEnvironment());

        $em = static::getContainer()
            ->get('doctrine')
            ->getManager('msp_server_manager');

        $northSeaConfig = $em->getRepository(GameConfigVersion::class)->findOneBy(['id' => 1]);
        $newGameSession = new GameList('testSession');
        $newGameSession->setGameConfigVersion($northSeaConfig);
        $newGameSession->setPasswordAdmin('test');

        $em->persist($newGameSession);
        $em->flush();


    }
}
