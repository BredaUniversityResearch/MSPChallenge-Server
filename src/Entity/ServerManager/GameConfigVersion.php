<?php

namespace App\Entity\ServerManager;

use App\Domain\Common\EntityEnums\GameConfigVersionVisibilityValue;
use App\Entity\Trait\LazyLoadersTrait;
use App\Repository\ServerManager\GameConfigVersionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\UniqueConstraint(name: 'uq_game_config_version', columns: ['game_config_files_id', 'version'])]
#[ORM\Entity(repositoryClass: GameConfigVersionRepository::class)]
class GameConfigVersion
{
    use LazyLoadersTrait;

    public const LAZY_LOADING_PROPERTY_GAME_CONFIG_COMPLETE_RAW = 'gameConfigCompleteRaw'; // value does not matter
    public const LAZY_LOADING_PROPERTY_GAME_CONFIG_COMPLETE = 'gameConfigComplete'; // value does not matter

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'gameConfigVersion')]
    #[ORM\JoinColumn(name: 'game_config_files_id', nullable: false)]
    private ?GameConfigFile $gameConfigFile = null;

    #[ORM\Column]
    private ?int $version = null;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $versionMessage = null;

    #[ORM\Column(length: 255)]
    private ?string $visibility = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?int $uploadTime = null;

    private ?string $uploadUserName = null;

    /**
     * User ID from MSP Challenge Authoriser.
     */
    #[ORM\Column]
    private ?int $uploadUser = null;

    /**
     * Unix timestamp
     */
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $lastPlayedTime = null;

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

    public function getUploadTime(): ?int
    {
        return $this->uploadTime;
    }

    public function setUploadTime(int $uploadTime): self
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

    public function getLastPlayedTime(): ?int
    {
        return $this->lastPlayedTime;
    }

    public function setLastPlayedTime(int $lastPlayedTime): self
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
        if (null !== $ll = $this->getLazyLoader(self::LAZY_LOADING_PROPERTY_GAME_CONFIG_COMPLETE)) {
            $this->gameConfigComplete ??= $ll();
        }
        return $this->gameConfigComplete;
    }

    public function setGameConfigComplete(?array $gameConfigComplete): self
    {
        $this->gameConfigComplete = $gameConfigComplete;

        return $this;
    }

    public function getGameConfigCompleteRaw(): ?string
    {
        if (null !== $ll = $this->getLazyLoader(self::LAZY_LOADING_PROPERTY_GAME_CONFIG_COMPLETE_RAW)) {
            $this->gameConfigCompleteRaw ??= $ll();
        }
        return $this->gameConfigCompleteRaw;
    }

    public function setGameConfigCompleteRaw(?string $gameConfigCompleteRaw): self
    {
        $this->gameConfigCompleteRaw = $gameConfigCompleteRaw;
        
        return $this;
    }

    /**
     * Get the value of uploadUserName
     */
    public function getUploadUserName(): ?string
    {
        return $this->uploadUserName;
    }

    /**
     * Set the value of uploadUserName
     *
     * @param string|null $uploadUserName
     * @return  self
     */
    public function setUploadUserName(?string $uploadUserName): self
    {
        $this->uploadUserName = $uploadUserName;

        return $this;
    }
}
