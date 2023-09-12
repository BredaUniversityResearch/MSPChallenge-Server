<?php
namespace App\Tests\Integration;

use App\VersionsProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Version\Version;

class VersioningSystemTest extends KernelTestCase
{

    public function testVersioningSystem(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $versionProvider = $container->get(VersionsProvider::class);

        $this->assertNotNull(
            $versionProvider->getVersion(),
            'Unable to retrieve server version'
        );
        $this->assertNotNull(
            $versionProvider->getComponentsVersions(),
            'Unable to retrieve server components versions'
        );
        $this->assertNotNull(
            $versionProvider->getConfigVersion(),
            'Unable to retrieve session config file version'
        );
        $this->assertNotNull(
            $versionProvider->getLowestClientVersion(),
            'Unable to retrieve lowest compatible client version'
        );

        $this->assertTrue(
            $versionProvider->checkCompatibleClient(
                $versionProvider->getLowestClientVersion()
            ),
            "Server {$versionProvider->getVersion()} & {$versionProvider->getLowestClientVersion()} incompatible?"
        );
        $this->expectException(\Exception::class);
        $versionProvider->checkCompatibleClient('1.0.0');
        $this->expectException(\Exception::class);
        $versionProvider->checkCompatibleClient('2.0.0');
        $this->expectException(\Exception::class);
        $versionProvider->checkCompatibleClient('3.0.0');
        $newerPatchClient = Version::fromString($versionProvider->getLowestClientVersion())->incrementPatch();
        $this->assertTrue(
            $versionProvider->checkCompatibleClient((string) $newerPatchClient),
            "Server {$versionProvider->getVersion()} & {$newerPatchClient} incompatible?"
        );
        $newerMinorClient = Version::fromString($versionProvider->getLowestClientVersion())->incrementMinor();
        if ($versionProvider->getVersionObject()->isGreaterOrEqualTo($newerMinorClient)) {
            $this->assertTrue(
                $versionProvider->checkCompatibleClient($newerMinorClient),
                "Server {$versionProvider->getVersion()} & {$newerMinorClient} incompatible?"
            );
        } else {
            $this->expectException(\Exception::class);
            $versionProvider->checkCompatibleClient($newerMinorClient);
        }
        $newerMajorClient = Version::fromString($versionProvider->getLowestClientVersion())->incrementMajor();
        $this->expectException(\Exception::class);
        $versionProvider->isCompatibleClient((string) $newerMajorClient);

        $newVersionProvider = new VersionsProvider(
            $container->get(KernelInterface::class),
            Version::fromString('4.9.0')
        );
        $this->assertTrue(
            $newVersionProvider->checkCompatibleClient($newVersionProvider->getLowestClientVersion()),
            "Server {$newVersionProvider->getVersion()} & {$newVersionProvider->getLowestClientVersion()} incompatible?"
        );
        $newVersionProvider = new VersionsProvider(
            $container->get(KernelInterface::class),
            Version::fromString('4.9.1')
        );
        $this->assertTrue(
            $newVersionProvider->checkCompatibleClient($newVersionProvider->getLowestClientVersion()),
            "Server {$newVersionProvider->getVersion()} & {$newVersionProvider->getLowestClientVersion()} incompatible?"
        );
        $newVersionProvider = new VersionsProvider(
            $container->get(KernelInterface::class),
            Version::fromString('4.10.0')
        );
        $this->assertTrue(
            $newVersionProvider->checkCompatibleClient($newVersionProvider->getLowestClientVersion()),
            "Server {$newVersionProvider->getVersion()} & {$newVersionProvider->getLowestClientVersion()} incompatible?"
        );
        $newVersionProvider = new VersionsProvider(
            $container->get(KernelInterface::class),
            Version::fromString('5.0.0')
        );
        $this->expectException(\Exception::class);
        $newVersionProvider->checkCompatibleClient($newVersionProvider->getLowestClientVersion());
    }
}
