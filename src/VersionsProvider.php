<?php
namespace App;

use Shivas\VersioningBundle\Formatter\GitDescribeFormatter;
use Shivas\VersioningBundle\Provider\ProviderInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Version\Exception\InvalidVersionString;
use Version\Version;

class VersionsProvider implements ProviderInterface
{
    private array $components;
    private string $configVersion = '1.0';
    private string $lowestClientVersion = '4.0.0';

    public function __construct(
        private readonly KernelInterface $kernel
    ) {
        // all components must have the same sub-array structure
        // and must have a version.txt file with a SemVer 2.0.0 compliant version in it
        $this->components = [
            'MSW' => [
                'sessionApiEndpoint' => '/api/Simulations/', // preceded by session ID, proceeded by method
                'folder' => $_ENV['WATCHDOG_WINDOWS_RELATIVE_PATH']
            ],
            'MEL' => [
                'sessionApiEndpoint' => '/api/MEL/', // preceded by session ID, proceeded by method
                'folder' => 'simulations/.NETFramework/MEL/v1/'
            ],
            'SEL' => [
                'sessionApiEndpoint' => '/api/SEL/', // preceded by session ID, proceeded by method
                'folder' => 'simulations/.NETFramework/SEL/v1/'
            ],
            'CEL' => [
                'sessionApiEndpoint' => '/api/CEL/', // preceded by session ID, proceeded by method
                'folder' => 'simulations/.NETFramework/CEL/v1/'
            ]
        ];
        $this->getComponentsVersions();
    }

    public function isSupported(): bool
    {
        return true;
    }

    public function getVersion(): string
    {
        return $this->getVersionTxtContents($this->kernel->getProjectDir() . DIRECTORY_SEPARATOR);
    }

    /**
     * @return array
     */
    public function getComponents(): array
    {
        return $this->components;
    }

    /**
     * @return string
     */
    public function getConfigVersion(): string
    {
        return $this->configVersion;
    }

    public function isCompatibleClient(string $clientVersion): bool
    {
        $clientVersion = Version::fromString($clientVersion);
        $serverVersion = Version::fromString($this->getVersion());
        if ($serverVersion->getMajor() == $clientVersion->getMajor()
            && $serverVersion->getMinor() == $clientVersion->getMinor()
        ) {
            // just a patch difference, fine!
            return true;
        }
        if ($clientVersion->isGreaterOrEqualTo($this->lowestClientVersion)
            && $serverVersion->isGreaterOrEqualTo($clientVersion)) {
            // so client is lower, but not too low, fine!
            return true;
        }
        return false;
    }

    /**
     * @return string
     */
    public function getLowestClientVersion(): string
    {
        return $this->lowestClientVersion;
    }

    private function getComponentsVersions(): void
    {
        $root = $this->kernel->getProjectDir() . DIRECTORY_SEPARATOR;
        foreach ($this->components as $component => $componentArr) {
            $this->components[$component]['version'] = $this->getFormattedVersion(
                $component,
                $this->getVersionTxtContents(
                    $root . $componentArr['folder']
                )
            );
        }
    }

    private function getVersionTxtContents($path): string
    {
        $result = file_get_contents($path . 'version.txt');
        if (false === $result) {
            throw new \RuntimeException(sprintf('Reading "%s" failed', $path . 'version.txt'));
        }

        return rtrim($result);
    }

    private function getFormattedVersion(string $component, string $versionString): string
    {
        try {
            if (str_starts_with(strtolower($versionString), 'v')) {
                $versionString = substr($versionString, 1);
            }
            $version = Version::fromString($versionString);
            return (new GitDescribeFormatter)->format($version);
        } catch (InvalidVersionString $e) {
            throw new \RuntimeException(
                "{$component} version {$versionString} does not follow SemVer standards"
            );
        }
    }
}
