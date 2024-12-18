<?php

namespace App\Entity\ServerManager;

use App\Domain\Common\EntityEnums\GameConfigVersionVisibilityValue;
use App\Repository\ServerManager\GameConfigVersionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\UniqueConstraint(name: 'uq_game_config_version', columns: ['game_config_files_id', 'version'])]
#[ORM\Entity(repositoryClass: GameConfigVersionRepository::class)]
class GameConfigVersion
{
    public const LAZY_LOADING_PROPERTY_GAME_CONFIG_COMPLETE_RAW = 'gameConfigCompleteRaw'; // value does not matter
    public const LAZY_LOADING_PROPERTY_GAME_CONFIG_COMPLETE = 'gameConfigComplete'; // value does not matter

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'game_config_files_id', nullable: false)]
    private ?GameConfigFile $gameConfigFile = null;

    #[ORM\Column]
    private ?int $version = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $versionMessage = null;

    #[ORM\Column(length: 255)]
    private ?string $visibility = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?string $uploadTime = null;

    /**
     * User ID from UserSpice.
     */
    #[ORM\Column]
    private ?int $uploadUser = null;

    /**
     * Unix timestamp
     */
    #[ORM\Column(type: Types::BIGINT)]
    private ?string $lastPlayedTime = null;

    /**
     * File path relative to the root config directory
     */
    #[ORM\Column(length: 255)]
    private ?string $filePath = null;

    /**
     * Region defined in the config file
     */
    #[ORM\Column(length: 45)]
    private ?string $region = null;

    /**
     * Compatible client versions. Formatted as "min-max"
     */
    #[ORM\Column(length: 45)]
    private ?string $clientVersions = null;

    private ?string $gameConfigCompleteRaw = null;

    private ?array $gameConfigComplete = null;

    private array $lazyLoaders = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGameConfigFile(): ?GameConfigFile
    {
        return $this->gameConfigFile;
    }

    public function setGameConfigFile(?GameConfigFile $gameConfigFile): self
    {
        $this->gameConfigFile = $gameConfigFile;

        return $this;
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function setVersion(int $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function getVersionMessage(): ?string
    {
        return $this->versionMessage;
    }

    public function setVersionMessage(?string $versionMessage): self
    {
        $this->versionMessage = $versionMessage;

        return $this;
    }

    public function getVisibility(): ?GameConfigVersionVisibilityValue
    {
        if (null === $this->visibility) {
            return null;
        }
        return new GameConfigVersionVisibilityValue($this->visibility);
    }

    public function setVisibility(GameConfigVersionVisibilityValue $visibilityValue): self
    {
        $this->visibility = (string)$visibilityValue;

        return $this;
    }

    public function getUploadTime(): ?string
    {
        return $this->uploadTime;
    }

    public function setUploadTime(string $uploadTime): self
    {
        $this->uploadTime = $uploadTime;

        return $this;
    }

    public function getUploadUser(): ?int
    {
        return $this->uploadUser;
    }

    public function setUploadUser(int $uploadUser): self
    {
        $this->uploadUser = $uploadUser;

        return $this;
    }

    public function getLastPlayedTime(): ?string
    {
        return $this->lastPlayedTime;
    }

    public function setLastPlayedTime(string $lastPlayedTime): self
    {
        $this->lastPlayedTime = $lastPlayedTime;

        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): self
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(string $region): self
    {
        $this->region = $region;

        return $this;
    }

    public function getClientVersions(): ?string
    {
        return $this->clientVersions;
    }

    public function setClientVersions(string $clientVersions): self
    {
        $this->clientVersions = $clientVersions;

        return $this;
    }

    public function getGameConfigComplete(): ?array
    {
        $this->gameConfigComplete ??= $this->lazyLoaders[self::LAZY_LOADING_PROPERTY_GAME_CONFIG_COMPLETE]();
        return $this->gameConfigComplete;
    }

    public function setGameConfigComplete(?array $gameConfigComplete): self
    {
        $this->gameConfigComplete = $gameConfigComplete;

        return $this;
    }

    public function getGameConfigCompleteRaw(): ?string
    {
        $this->gameConfigCompleteRaw ??= $this->lazyLoaders[self::LAZY_LOADING_PROPERTY_GAME_CONFIG_COMPLETE_RAW]();
        return $this->gameConfigCompleteRaw;
    }

    public function setGameConfigCompleteRaw(?string $gameConfigCompleteRaw): self
    {
        $this->gameConfigCompleteRaw = $gameConfigCompleteRaw;
        return $this;
    }

    public function hasLazyLoader(string $propertyName): bool
    {
        return array_key_exists($propertyName, $this->lazyLoaders);
    }

    public function setLazyLoader(string $propertyName, callable $loader): self
    {
        $this->lazyLoaders[$propertyName] = $loader;
        return $this;
    }
}
