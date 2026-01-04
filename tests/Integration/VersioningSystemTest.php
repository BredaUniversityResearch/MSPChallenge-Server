<?php
namespace App\Tests\Integration;

use App\IncompatibleClientException;
use App\VersionsProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Version\Version;

class VersioningSystemTest extends KernelTestCase
{
    private VersionsProvider $versionProvider;

    /**
     * @throws IncompatibleClientException
     */
    public function testVersioningSystem(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->versionProvider = $container->get(VersionsProvider::class);

        $this->assertNotNull(
            $this->versionProvider->getVersion(),
            'Unable to retrieve server version'
        );
        $this->assertNotNull(
            $this->versionProvider->getComponentsVersions(),
            'Unable to retrieve server components versions'
        );
        $this->assertNotNull(
            $this->versionProvider->getConfigVersion(),
            'Unable to retrieve session config file version'
        );
        $this->assertNotNull(
            $this->versionProvider->getLowestClientVersion(),
            'Unable to retrieve lowest compatible client version'
        );

        $this->assertTrue(
            $this->versionProvider->checkCompatibleClient(
                $this->versionProvider->getLowestClientVersion()
            ),
            "Server {$this->versionProvider->getVersion()} &
            {$this->versionProvider->getLowestClientVersion()} incompatible?"
        );
        $this->assertIncompatibleClient('1.0.0');
        $this->assertIncompatibleClient('2.0.0');
        $this->assertIncompatibleClient('3.0.0');
        $newerPatchClient = Version::fromString($this->versionProvider->getLowestClientVersion())->incrementPatch();
        $this->assertTrue(
            $this->versionProvider->checkCompatibleClient((string) $newerPatchClient),
            "Server {$this->versionProvider->getVersion()} & {$newerPatchClient} incompatible?"
        );
        $newerMinorClient = Version::fromString($this->versionProvider->getLowestClientVersion())->incrementMinor();
        $this->assertTrue(
            $this->versionProvider->checkCompatibleClient($newerMinorClient),
            "Server {$this->versionProvider->getVersion()} & {$newerMinorClient} incompatible?"
        );
        $newerMajorClient = Version::fromString($this->versionProvider->getLowestClientVersion())->incrementMajor();
        $this->assertIncompatibleClient((string) $newerMajorClient);

        $this->versionProvider = new VersionsProvider(
            $container->getParameter('app.version_filename'),
            $container->get(KernelInterface::class),
            Version::fromString('4.9.0')
        );
        $this->versionProvider->setLowestClientVersion(Version::fromString('4.0.0'));
        $this->assertTrue(
            $this->versionProvider->checkCompatibleClient($this->versionProvider->getLowestClientVersion()),
            "Server {$this->versionProvider->getVersion()} &
            {$this->versionProvider->getLowestClientVersion()} incompatible?"
        );
        $this->versionProvider = new VersionsProvider(
            $container->getParameter('app.version_filename'),
            $container->get(KernelInterface::class),
            Version::fromString('4.9.1')
        );
        $this->versionProvider->setLowestClientVersion(Version::fromString('4.0.0'));
        $this->assertTrue(
            $this->versionProvider->checkCompatibleClient($this->versionProvider->getLowestClientVersion()),
            "Server {$this->versionProvider->getVersion()} &
            {$this->versionProvider->getLowestClientVersion()} incompatible?"
        );
        $this->versionProvider = new VersionsProvider(
            $container->getParameter('app.version_filename'),
            $container->get(KernelInterface::class),
            Version::fromString('4.10.0')
        );
        $this->versionProvider->setLowestClientVersion(Version::fromString('4.0.0'));
        $this->assertTrue(
            $this->versionProvider->checkCompatibleClient($this->versionProvider->getLowestClientVersion()),
            "Server {$this->versionProvider->getVersion()} &
            {$this->versionProvider->getLowestClientVersion()} incompatible?"
        );
        $this->versionProvider = new VersionsProvider(
            $container->getParameter('app.version_filename'),
            $container->get(KernelInterface::class),
            Version::fromString('5.0.0')
        );
        $this->versionProvider->setLowestClientVersion(Version::fromString('4.0.0'));
        $this->assertIncompatibleClient($this->versionProvider->getLowestClientVersion());
    }

    private function assertIncompatibleClient($version): void
    {
        try {
            $this->versionProvider->checkCompatibleClient($version);
        } catch (IncompatibleClientException $exception) {
            $this->assertEquals('Client not compatible', $exception->getMessage());
            return;
        }
        $this->fail('IncompatibleClientException exception was not thrown.');
    }
}
