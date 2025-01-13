<?php

namespace App\Tests\ServerManager;

use App\Entity\ServerManager\GameGeoServer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BUasGeoServerTest extends KernelTestCase
{
    public function testBUasGeoServer(): void
    {
        $kernel = self::bootKernel();
        $this->assertSame('test', $kernel->getEnvironment());

        $em = static::getContainer()
            ->get('doctrine')
            ->getManager('msp_server_manager');

        $geoServer = $em->getRepository(GameGeoServer::class)->findOneBy(['id' => 1]);
        $this->assertSame('ReadUser', $geoServer->getUsername());
    }
}
