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
            $versionProvider->isCompatibleClient(
                $versionProvider->getLowestClientVersion()
            ),
            "Server {$versionProvider->getVersion()} & {$versionProvider->getLowestClientVersion()} incompatible?"
        );
        $this->assertFalse(
            $versionProvider->isCompatibleClient('1.0.0'),
            "Server {$versionProvider->getVersion()} & 1.0.0 compatible?"
        );
        $this->assertFalse(
            $versionProvider->isCompatibleClient('2.0.0'),
            "Server {$versionProvider->getVersion()} & 2.0.0 compatible?"
        );
        $this->assertFalse(
            $versionProvider->isCompatibleClient('3.0.0'),
            "Server {$versionProvider->getVersion()} & 3.0.0 compatible?"
        );

        $newerPatchClient = Version::fromString($versionProvider->getLowestClientVersion())->incrementPatch();
        $this->assertTrue(
            $versionProvider->isCompatibleClient((string) $newerPatchClient),
            "Server {$versionProvider->getVersion()} & {$newerPatchClient} incompatible?"
        );
        $newerMinorClient = Version::fromString($versionProvider->getLowestClientVersion())->incrementMinor();
        if ($versionProvider->getVersionObject()->isGreaterOrEqualTo($newerMinorClient)) {
            $this->assertTrue(
                $versionProvider->isCompatibleClient($newerMinorClient),
                "Server {$versionProvider->getVersion()} & {$newerMinorClient} incompatible?"
            );
        } else {
            $this->assertFalse(
                $versionProvider->isCompatibleClient($newerMinorClient),
                "Server {$versionProvider->getVersion()} & {$newerMinorClient} compatible?"
            );
        }
        $newerMajorClient = Version::fromString($versionProvider->getLowestClientVersion())->incrementMajor();
        $this->assertFalse(
            $versionProvider->isCompatibleClient((string) $newerMajorClient),
            "Server {$versionProvider->getVersion()} & {$newerMajorClient} compatible?"
        );

        $newVersionProvider = new VersionsProvider(
            $container->get(KernelInterface::class),
            Version::fromString('4.9.0')
        );
        $this->assertTrue(
            $newVersionProvider->isCompatibleClient($newVersionProvider->getLowestClientVersion()),
            "Server {$newVersionProvider->getVersion()} & {$newVersionProvider->getLowestClientVersion()} incompatible?"
        );
        $newVersionProvider = new VersionsProvider(
            $container->get(KernelInterface::class),
            Version::fromString('4.9.1')
        );
        $this->assertTrue(
            $newVersionProvider->isCompatibleClient($newVersionProvider->getLowestClientVersion()),
            "Server {$newVersionProvider->getVersion()} & {$newVersionProvider->getLowestClientVersion()} incompatible?"
        );
        $newVersionProvider = new VersionsProvider(
            $container->get(KernelInterface::class),
            Version::fromString('4.10.0')
        );
        $this->assertTrue(
            $newVersionProvider->isCompatibleClient($newVersionProvider->getLowestClientVersion()),
            "Server {$newVersionProvider->getVersion()} & {$newVersionProvider->getLowestClientVersion()} incompatible?"
        );
        $newVersionProvider = new VersionsProvider(
            $container->get(KernelInterface::class),
            Version::fromString('5.0.0')
        );
        $this->assertFalse(
            $newVersionProvider->isCompatibleClient($newVersionProvider->getLowestClientVersion()),
            "Server {$newVersionProvider->getVersion()} & {$newVersionProvider->getLowestClientVersion()} compatible?"
        );
    }
}
