<?php
namespace App;

use Shivas\VersioningBundle\Provider\ProviderInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Version\Exception\InvalidVersionString;
use Version\Version;

class VersionsProvider implements ProviderInterface
{
    private Version $version;
    private array $components;
    private array $componentsVersions; // will be an array of Version objects
    private Version $configVersion;
    private Version $lowestClientVersion;

    public function __construct(
        private readonly KernelInterface $kernel,
        ?Version $override = null
    ) {
        $this->setVersion($override);
        // all components must have the same sub-array structure
        // and must have a version.txt file with a SemVer 2.0.0 compliant version in it
        $this->components = [
            'MSW' => $_ENV['WATCHDOG_WINDOWS_RELATIVE_PATH'].'MSWdata/',
            'MEL' => $_ENV['WATCHDOG_WINDOWS_RELATIVE_PATH'].'MELdata/',
            'SEL' => $_ENV['WATCHDOG_WINDOWS_RELATIVE_PATH'].'SELdata/',
            'CEL' => $_ENV['WATCHDOG_WINDOWS_RELATIVE_PATH'].'CELdata/',
            'REL' => $_ENV['WATCHDOG_WINDOWS_RELATIVE_PATH'].'RELdata/',
        ];
        $this->setComponentsVersions();
        $this->configVersion = Version::fromString('2.0.0');
        $this->lowestClientVersion = Version::fromString('5.0.0');
    }

    public function isSupported(): bool
    {
        return true;
    }

    public function getVersion(): string
    {
        return (string) $this->getVersionObject();
    }

    public function getVersionObject(): Version
    {
        return $this->version;
    }

    public function getConfigVersion(): string
    {
        return $this->configVersion;
    }

    public function getLowestClientVersion(): string
    {
        return $this->lowestClientVersion;
    }

    public function getComponentsVersions(): array
    {
        return $this->componentsVersions;
    }

    public function getComponents(): array
    {
        return $this->components;
    }

    /**
     * @throws IncompatibleClientException
     * @throws InvalidVersionString
     */
    public function checkCompatibleClient(?string $clientVersion): bool
    {
        if (is_null($clientVersion)) {
            throw new InvalidVersionString('Client must send Msp-Client-Version');
        }
        $clientVersion = Version::fromString($clientVersion);
        $serverVersion = Version::fromString($this->getVersion());
        if ($serverVersion->getMajor() == $clientVersion->getMajor()
            && $serverVersion->getMinor() == $clientVersion->getMinor()
        ) {
            // just a patch difference between client and server: fine!
            return true;
        }
        if ($clientVersion->isGreaterOrEqualTo($this->lowestClientVersion)
            && $serverVersion->getMajor() == $clientVersion->getMajor()
        ) {
            // so client is above minimum required and of the same major: fine!
            return true;
        }
        throw new IncompatibleClientException('Client not compatible');
    }

    private function setVersion(?Version $override = null): void
    {
        $this->version = $override ?? $this->getFormattedVersion(
            'Server',
            $this->getVersionTxtContents($this->kernel->getProjectDir() . DIRECTORY_SEPARATOR)
        );
    }

    private function setComponentsVersions(): void
    {
        $root = $this->kernel->getProjectDir() . DIRECTORY_SEPARATOR;
        foreach ($this->components as $component => $folder) {
            $this->componentsVersions[$component] = $this->getFormattedVersion(
                $component,
                $this->getVersionTxtContents(
                    $root . $folder
                )
            );
        }
    }

    private function getVersionTxtContents(string $path): string
    {
        $result = file_get_contents($path . 'version.txt');
        if (false === $result) {
            throw new \RuntimeException(sprintf('Reading "%s" failed', $path . 'version.txt'));
        }

        return rtrim($result);
    }

    private function getFormattedVersion(string $component, string $versionString): Version
    {
        try {
            if (str_starts_with(strtolower($versionString), 'v')) {
                $versionString = substr($versionString, 1);
            }
            return Version::fromString($versionString);
        } catch (InvalidVersionString $e) {
            throw new \RuntimeException(
                "{$component} version {$versionString} does not follow SemVer standards"
            );
        }
    }
}
