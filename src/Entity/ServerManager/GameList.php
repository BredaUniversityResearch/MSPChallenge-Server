<?php

namespace App\Entity\ServerManager;

use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Common\EntityEnums\GameTransitionStateValue;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Common\EntityEnums\GameVisibilityValue;
use App\Entity\EntityBase;
use App\Repository\ServerManager\GameListRepository;
use App\Entity\SessionAPI\Game;
use App\Validator as AcmeAssert;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use function App\isBase64Encoded;

#[ORM\Entity(repositoryClass: GameListRepository::class)]
class GameList extends EntityBase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 128)]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $name = null;

    #[Assert\Expression(
        "this.getGameConfigVersion() !== null or this.getGameSave() !== null",
        "When creating a new session, a config file needs to be chosen."
    )]
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
    // @phpstan-ignore-next-line int|null but database expects int
    private ?int $gameCreationTime = null;

    #[ORM\Column]
    // @phpstan-ignore-next-line int|null but database expects int
    private ?int $gameStartYear = null;

    #[ORM\Column]
    // @phpstan-ignore-next-line int|null but database expects int
    private ?int $gameEndMonth = null;

    #[ORM\Column(type: Types::INTEGER, length: 11, nullable: true)]
    private ?int $gameTransitionMonth = null;

    #[ORM\Column]
    private int $gameCurrentMonth = -1;

    #[ORM\Column(type: Types::BIGINT)]
    // @phpstan-ignore-next-line int|null but database expects int
    private ?int $gameRunningTilTime = null;

    #[Assert\NotBlank]
    #[AcmeAssert\ContainsValidExternalUsers]
    #[ORM\Column(type: Types::TEXT)]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $passwordAdmin = null;

    #[AcmeAssert\ContainsValidExternalUsers]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $passwordPlayer = null;

    #[ORM\Column(length: 255)]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $sessionState = null;

    #[ORM\Column(nullable: true, enumType: GameTransitionStateValue::class)]
    private ?GameTransitionStateValue $gameTransitionState = null;

    #[ORM\Column(enumType: GameStateValue::class, options: ['default' => GameStateValue::SETUP->value])]
    private GameStateValue $gameState = GameStateValue::SETUP;

    #[ORM\Column(length: 255)]
    // @phpstan-ignore-next-line string|null but database expects string
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

    private ?Game $runningGame = null;

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

    public function setName(?string $name): self
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

    public function getGameCreationTimePretty(): string
    {
        return $this->makeTimePretty($this->gameCreationTime);
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

    public function getGameTransitionMonth(): ?int
    {
        return $this->gameTransitionMonth;
    }

    public function setGameTransitionMonth(?int $gameTransitionMonth): self
    {
        $this->gameTransitionMonth = $gameTransitionMonth;
        return $this;
    }

    public function getGameTransitionMonthPretty(): ?string
    {
        if (null === $this->gameTransitionMonth) {
            return null;
        }
        return $this->makeDatePretty($this->gameTransitionMonth);
    }

    public function getGameCurrentMonth(): int
    {
        // taken from api/v1/Game.php GetCurrentMonthAsId()
        if ($this->gameState == GameStateValue::SETUP) {
            $this->gameCurrentMonth = -1;
        }
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

    private function makeTimePretty(int $unixTimeStamp): string
    {
        return \DateTimeImmutable::createFromFormat('U', (string) $unixTimeStamp)
            ->format('j M Y G:i');
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

    public function getGameRunningTilTimePretty(): string
    {
        return $this->makeTimePretty($this->gameRunningTilTime);
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
        $this->passwordAdmin = $this->checkPasswordFormat('password_admin', $passwordAdmin);

        return $this;
    }

    public function getPasswordPlayer(): ?string
    {
        return $this->passwordPlayer;
    }


    public function encodePasswords(): self
    {
        if (!isBase64Encoded($this->passwordAdmin)) {
            $this->passwordAdmin = base64_encode($this->passwordAdmin);
        }
        if (!isBase64Encoded($this->passwordPlayer)) {
            $this->passwordPlayer = base64_encode($this->passwordPlayer);
        }

        return $this;
    }

    public function decodePasswords(): self
    {
        if (isBase64Encoded($this->passwordAdmin)) {
            $this->passwordAdmin = base64_decode($this->passwordAdmin);
        }
        if (isBase64Encoded($this->passwordPlayer)) {
            $this->passwordPlayer = base64_decode($this->passwordPlayer);
        }

        return $this;
    }

    public function setPasswordPlayer(?string $passwordPlayer): self
    {
        $this->passwordPlayer = $this->checkPasswordFormat('password_player', $passwordPlayer);

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

    public function getGameTransitionState(): ?GameTransitionStateValue
    {
        return $this->gameTransitionState;
    }

    public function setGameTransitionState(?GameTransitionStateValue $gameTransitionState): self
    {
        $this->gameTransitionState = $gameTransitionState;
        return $this;
    }

    public function getGameState(): GameStateValue
    {
        return $this->gameState;
    }

    public function setGameState(GameStateValue $gameState): self
    {
        $this->gameState = $gameState;

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

    /**
     * Get the value of runningGame
     */
    public function getRunningGame(): ?Game
    {
        return $this->runningGame;
    }

    /**
     * Set the value of runningGame
     *
     * @return  self
     */
    public function setRunningGame($runningGame): self
    {
        $this->runningGame = $runningGame;

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

    public function getCountries(): array
    {
        if (!is_null($this->getRunningGame())) {
            $configData = $this->getRunningGame()->getRunningGameConfigFileContents();
        } elseif (!is_null($this->getGameConfigVersion())) {
            $configData = $this->getGameConfigVersion()->getGameConfigComplete();
        } else {
            return [];
        }
        $countries = [];
        foreach ($configData['datamodel']['meta'] as $layerMeta) {
            if ($layerMeta['layer_name'] == $configData['datamodel']['countries']) {
                foreach ($layerMeta['layer_type'] as $country) {
                    $countries[] = [
                        'country_id' => $country['value'],
                        'country_name' => $country['displayName'],
                        'country_colour' => $country['polygonColor'],
                    ];
                }
                break;
            }
        }
        return $countries;
    }
}
