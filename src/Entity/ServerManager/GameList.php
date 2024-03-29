<?php

namespace App\Entity\ServerManager;

use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Common\EntityEnums\GameVisibilityValue;
use App\Repository\ServerManager\GameListRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GameListRepository::class)]
class GameList
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 128)]
    private ?string $name = null;

    #[Assert\NotNull]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?GameConfigVersion $gameConfigVersion = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?GameServer $gameServer = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'game_geoserver_id', nullable: true)]
    private ?GameGeoServer $gameGeoServer = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'watchdog_server_id', nullable: false)]
    private ?GameWatchdogServer $gameWatchdogServer = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?int $gameCreationTime = null;

    #[ORM\Column]
    private ?int $gameStartYear = null;

    #[ORM\Column]
    private ?int $gameEndMonth = null;

    #[ORM\Column]
    private int $gameCurrentMonth = 0;

    #[ORM\Column(type: Types::BIGINT)]
    private ?int $gameRunningTilTime = null;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::TEXT)]
    private ?string $passwordAdmin = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
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
    private int $demoSession = 0;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $apiAccessToken = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(name: 'save_id', nullable: true, options: ['default' => null])]
    private ?GameSave $gameSave = null;

    #[ORM\Column(length: 45, nullable: true)]
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

    public function getGameServer(): ?GameServer
    {
        return $this->gameServer;
    }

    public function setGameServer(?GameServer $gameServer): self
    {
        $this->gameServer = $gameServer;

        return $this;
    }

    public function getGameGeoServer(): ?GameGeoServer
    {
        return $this->gameGeoServer;
    }

    public function setGameGeoServer(?GameGeoServer $gameGeoServer): self
    {
        $this->gameGeoServer = $gameGeoServer;

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

    public function getGameCreationTime(): ?int
    {
        return $this->gameCreationTime;
    }

    public function setGameCreationTime(int $gameCreationTime): self
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

    public function getGameEndMonthPretty(): string
    {
        return $this->makeDatePretty($this->gameEndMonth);
    }

    public function setGameEndMonth(int $gameEndMonth): self
    {
        $this->gameEndMonth = $gameEndMonth;

        return $this;
    }

    public function getGameCurrentMonth(): int
    {
        // taken from api/v1/Game.php GetCurrentMonthAsId()
        if ($this->gameState == 'SETUP') {
            $this->gameCurrentMonth = -1;
        }
        return $this->gameCurrentMonth;
    }

    public function getGameCurrentMonthPretty(): string
    {
        return $this->makeDatePretty($this->gameCurrentMonth);
    }

    private function makeDatePretty($month): string
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

    public function getGameRunningTilTime(): ?int
    {
        return $this->gameRunningTilTime;
    }

    public function setGameRunningTilTime(int $gameRunningTilTime): self
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

    public function getDemoSession(): int
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

    public function getGameSave(): ?GameSave
    {
        return $this->gameSave;
    }

    public function setGameSave(GameSave $gameSave): self
    {
        $this->gameSave = $gameSave;

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

    public function checkPasswordFormat(string $adminorplayer, string $string): bool|string
    {
        if (is_object(json_decode($string))) {
            // backwards compatibility
            $stringDecoded = json_decode($string, true);
            if (isset($stringDecoded['admin'])) {
                if (isset($stringDecoded['admin']['password'])) {
                    $stringDecoded['admin']['value'] = $stringDecoded['admin']['password'];
                    unset($stringDecoded['admin']['password']);
                } elseif (isset($stringDecoded['admin']['users'])) {
                    if (is_array($stringDecoded['admin']['users'])) {
                        $stringDecoded['admin']['users'] = implode(' ', $stringDecoded['admin']['users']);
                    }
                    $stringDecoded['admin']['value'] = $stringDecoded['admin']['users'];
                    unset($stringDecoded['admin']['users']);
                }
                if (isset($stringDecoded['region']['password'])) {
                    $stringDecoded['region']['value'] = $stringDecoded['region']['password'];
                    unset($stringDecoded['region']['password']);
                } elseif (isset($stringDecoded['region']['users'])) {
                    if (is_array($stringDecoded['region']['users'])) {
                        $stringDecoded['region']['users'] = implode(' ', $stringDecoded['region']['users']);
                    }
                    $stringDecoded['region']['value'] = $stringDecoded['region']['users'];
                    unset($stringDecoded['region']['users']);
                }
            } elseif (isset($stringDecoded['password'])) {
                $stringDecoded['value'] = $stringDecoded['password'];
                unset($stringDecoded['password']);
            } elseif (isset($stringDecoded['users'])) {
                if (is_array($stringDecoded['users'])) {
                    $stringDecoded['users'] = implode(' ', $stringDecoded['users']);
                }
                $stringDecoded['value'] = $stringDecoded['users'];
                unset($stringDecoded['users']);
            }

            return json_encode($stringDecoded);
        }

        // only used when creating new session or loading a save from pre-beta8
        if ('password_admin' == $adminorplayer) {
            $newArray['admin']['provider'] = 'local';
            $newArray['admin']['value'] = (string) $string;
            $newArray['region']['provider'] = 'local';
            $newArray['region']['value'] = (string) $string;
        } else {
            $newArray['provider'] = 'local';
            $countries = $this->getCountries();
            foreach ($countries as $country_data) {
                $newArray['value'][$country_data['country_id']] = (string) $string;
            }
        }

        return json_encode($newArray);
    }

    private function getCountries(): array
    {
        if (!is_null($this->getGameSave())) { // session eminates from a save
            $configData = []; // to do
        } else { // session eminates from a config file, so from scratch
            $configData = $this->getGameConfigVersion()->getGameConfigComplete();
        }

        $countries = [];
        if (!isset($configData['datamodel']) || !isset($configData['datamodel']['meta'])) {
            return $countries;
        }

        foreach ($configData['datamodel']['meta'] as $layerMeta) {
            if ($layerMeta['layer_name'] == $configData['datamodel']['countries']) {
                foreach ($layerMeta['layer_type'] as $country) {
                    $countries[] = [
                        'country_id' => $country['value'],
                        'country_name' => $country['displayName'],
                        'country_colour' => $country['polygonColor'],
                    ];
                }
            }
        }

        return $countries;
    }
}
