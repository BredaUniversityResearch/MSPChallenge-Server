<?php

namespace App\Entity\ServerManager;

use App\Domain\Common\EntityEnums\GameSaveTypeValue;
use App\Domain\Common\EntityEnums\GameSaveVisibilityValue;
use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Common\EntityEnums\GameVisibilityValue;
use App\Repository\ServerManager\GameSaveRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'game_saves')]
#[ORM\Entity(repositoryClass: GameSaveRepository::class)]
class GameSave
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 128)]
    private ?string $name = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?GameConfigVersion $gameConfigVersion = null;

    #[ORM\Column(length: 45)]
    private ?string $gameConfigFilesFilename = null;

    #[ORM\Column(length: 45)]
    private ?string $gameConfigVersionsRegion = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?GameServer $gameServer = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'watchdog_server_id', nullable: false)]
    private ?GameWatchdogServer $gameWatchdogServer = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?string $gameCreationTime = null;

    #[ORM\Column]
    private ?int $gameStartYear = null;

    #[ORM\Column]
    private ?int $gameEndMonth = null;

    #[ORM\Column]
    private ?int $gameCurrentMonth = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?string $gameRunningTilTime = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $passwordAdmin = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $passwordPlayer = null;

    #[ORM\Column(length: 255)]
    private ?string $sessionState = null;

    #[ORM\Column(length: 255)]
    private ?string $gameState = null;

    #[ORM\Column(length: 255)]
    private ?string $gameVisibility = null;

    #[ORM\Column(nullable: true)]
    private ?int $playersActive = null;

    #[ORM\Column(nullable: true)]
    private ?int $playersPastHour = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $demoSession = null;

    #[ORM\Column(length: 32)]
    private ?string $apiAccessToken = null;

    #[ORM\Column(length: 255)]
    private ?string $saveType = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $saveNotes = null;

    #[ORM\Column(length: 255)]
    private ?string $saveVisibility = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[ORM\Version] # converts the type 'datetime' to 'timestamp'.
    private ?\DateTimeInterface $saveTimestamp = null;

    #[ORM\Column(length: 45)]
    private ?string $serverVersion = null;

    /**
     * @param int|null $id
     */
    public function __construct(?int $id = null)
    {
        $this->id = $id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getGameConfigVersion(): ?GameConfigVersion
    {
        return $this->gameConfigVersion;
    }

    public function setGameConfigVersion(?GameConfigVersion $gameConfigVersion): self
    {
        $this->gameConfigVersion = $gameConfigVersion;

        return $this;
    }

    public function getGameConfigFilesFilename(): ?string
    {
        return $this->gameConfigFilesFilename;
    }

    public function setGameConfigFilesFilename(string $gameConfigFilesFilename): self
    {
        $this->gameConfigFilesFilename = $gameConfigFilesFilename;

        return $this;
    }

    public function getGameConfigVersionsRegion(): ?string
    {
        return $this->gameConfigVersionsRegion;
    }

    public function setGameConfigVersionsRegion(string $gameConfigVersionsRegion): self
    {
        $this->gameConfigVersionsRegion = $gameConfigVersionsRegion;

        return $this;
    }

    public function getGameServer(): ?GameServer
    {
        return $this->gameServer;
    }

    public function setGameServer(?GameServer $gameServer): self
    {
        $this->gameServer = $gameServer;

        return $this;
    }

    public function getGameWatchdogServer(): ?GameWatchdogServer
    {
        return $this->gameWatchdogServer;
    }

    public function setGameWatchdogServer(?GameWatchdogServer $gameWatchdogServer): self
    {
        $this->gameWatchdogServer = $gameWatchdogServer;

        return $this;
    }

    public function getGameCreationTime(): ?string
    {
        return $this->gameCreationTime;
    }

    public function setGameCreationTime(string $gameCreationTime): self
    {
        $this->gameCreationTime = $gameCreationTime;

        return $this;
    }

    public function getGameStartYear(): ?int
    {
        return $this->gameStartYear;
    }

    public function setGameStartYear(int $gameStartYear): self
    {
        $this->gameStartYear = $gameStartYear;

        return $this;
    }

    public function getGameEndMonth(): ?int
    {
        return $this->gameEndMonth;
    }

    public function setGameEndMonth(int $gameEndMonth): self
    {
        $this->gameEndMonth = $gameEndMonth;

        return $this;
    }

    public function getGameCurrentMonth(): ?int
    {
        return $this->gameCurrentMonth;
    }

    public function getGameCurrentMonthPretty(): string
    {
        return $this->makeDatePretty($this->gameCurrentMonth);
    }

    private function makeDatePretty(int $month): string
    {
        return \DateTimeImmutable::createFromFormat('m Y', '1 '.$this->gameStartYear)
            ->add(\DateInterval::createFromDateString($month.' month'))
            ->format('M Y');
    }

    public function setGameCurrentMonth(int $gameCurrentMonth): self
    {
        $this->gameCurrentMonth = $gameCurrentMonth;

        return $this;
    }

    public function getGameRunningTilTime(): ?string
    {
        return $this->gameRunningTilTime;
    }

    public function setGameRunningTilTime(string $gameRunningTilTime): self
    {
        $this->gameRunningTilTime = $gameRunningTilTime;

        return $this;
    }

    public function getPasswordAdmin(): ?string
    {
        return $this->passwordAdmin;
    }

    public function setPasswordAdmin(string $passwordAdmin): self
    {
        $this->passwordAdmin = $passwordAdmin;

        return $this;
    }

    public function getPasswordPlayer(): ?string
    {
        return $this->passwordPlayer;
    }

    public function setPasswordPlayer(?string $passwordPlayer): self
    {
        $this->passwordPlayer = $passwordPlayer;

        return $this;
    }

    public function getSessionState(): ?GameSessionStateValue
    {
        if (null === $this->sessionState) {
            return null;
        }
        return new GameSessionStateValue($this->sessionState);
    }

    public function setSessionState(GameSessionStateValue $sessionState): self
    {
        $this->sessionState = (string)$sessionState;

        return $this;
    }

    public function getGameState(): ?GameStateValue
    {
        if (null === $this->gameState) {
            return null;
        }
        return new GameStateValue($this->gameState);
    }

    public function setGameState(GameStateValue $gameState): self
    {
        $this->gameState = (string)$gameState;

        return $this;
    }

    public function getGameVisibility(): ?GameVisibilityValue
    {
        if (null === $this->gameVisibility) {
            return null;
        }
        return new GameVisibilityValue($this->gameVisibility);
    }

    public function setGameVisibility(GameVisibilityValue $gameVisibility): self
    {
        $this->gameVisibility = (string)$gameVisibility;

        return $this;
    }

    public function getPlayersActive(): ?int
    {
        return $this->playersActive;
    }

    public function setPlayersActive(?int $playersActive): self
    {
        $this->playersActive = $playersActive;

        return $this;
    }

    public function getPlayersPastHour(): ?int
    {
        return $this->playersPastHour;
    }

    public function setPlayersPastHour(?int $playersPastHour): self
    {
        $this->playersPastHour = $playersPastHour;

        return $this;
    }

    public function getDemoSession(): ?int
    {
        return $this->demoSession;
    }

    public function setDemoSession(int $demoSession): self
    {
        $this->demoSession = $demoSession;

        return $this;
    }

    public function getApiAccessToken(): ?string
    {
        return $this->apiAccessToken;
    }

    public function setApiAccessToken(?string $apiAccessToken): self
    {
        $this->apiAccessToken = $apiAccessToken;

        return $this;
    }

    public function getSaveType(): ?GameSaveTypeValue
    {
        if (null === $this->saveType) {
            return null;
        }
        return new GameSaveTypeValue($this->saveType);
    }

    public function setSaveType(GameSaveTypeValue $saveType): self
    {
        $this->saveType = (string)$saveType;

        return $this;
    }

    public function getSaveNotes(): ?string
    {
        return $this->saveNotes;
    }

    public function setSaveNotes(string $saveNotes): self
    {
        $this->saveNotes = $saveNotes;

        return $this;
    }

    public function addToSaveNotes(string $saveNotes): self
    {
        $this->saveNotes = $this->getSaveNotes().$saveNotes;

        return $this;
    }

    public function getSaveVisibility(): ?GameSaveVisibilityValue
    {
        if (null === $this->saveVisibility) {
            return null;
        }
        return new GameSaveVisibilityValue($this->saveVisibility);
    }

    public function setSaveVisibility(GameSaveVisibilityValue $saveVisibility): self
    {
        $this->saveVisibility = (string)$saveVisibility;

        return $this;
    }

    public function getSaveTimestampPretty(): ?string
    {
        return $this->getSaveTimestamp()->format('j M Y G:i');
    }

    public function getSaveTimestamp(): ?\DateTimeInterface
    {
        return $this->saveTimestamp;
    }

    public function setSaveTimestamp(\DateTimeInterface $saveTimestamp): self
    {
        $this->saveTimestamp = $saveTimestamp;

        return $this;
    }

    public function getServerVersion(): ?string
    {
        return $this->serverVersion;
    }

    public function setServerVersion(?string $serverVersion): self
    {
        $this->serverVersion = $serverVersion;

        return $this;
    }
}
