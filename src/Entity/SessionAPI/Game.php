<?php

namespace App\Entity\SessionAPI;

use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Common\EntityEnums\GameTransitionStateValue;
use App\Repository\SessionAPI\GameRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameRepository::class)]

class Game
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER, length: 11)]
    // @phpstan-ignore-next-line int|null but database expects int
    private ?int $gameId;

    #[ORM\Column(type: Types::INTEGER, length: 5, options: ['default' => 2010])]
    private int $gameStart = 2010;

    #[ORM\Column(nullable: true, enumType: GameTransitionStateValue::class)]
    private ?GameTransitionStateValue $gameTransitionState = null;

    #[ORM\Column(enumType: GameStateValue::class, options: ['default' => GameStateValue::SETUP->value])]
    private GameStateValue $gameState = GameStateValue::SETUP;

    #[ORM\Column(type: Types::FLOAT, nullable: true, options: ['default' => 0])]
    private ?float $gameLastupdate = 0;

    #[ORM\Column(type: Types::INTEGER, length: 11, nullable: true)]
    private ?int $gameTransitionMonth = null;

    #[ORM\Column(type: Types::INTEGER, length: 11, options: ['default' => -1])]
    private int $gameCurrentmonth = -1;

    #[ORM\Column(type: Types::SMALLINT, length: 4, options: ['default' => 0])]
    private int $gameEnergyupdate = 0;

    #[ORM\Column(type: Types::INTEGER, length: 11, options: ['default' => 36])]
    private int $gamePlanningGametime = 36;

    #[ORM\Column(type: Types::INTEGER, length: 11, options: ['default' => 1])]
    private int $gamePlanningRealtime = 1;

    #[ORM\Column(type: Types::STRING, length: 256, options: ['default' => '0'])]
    private string $gamePlanningEraRealtime = '0';

    #[ORM\Column(type: Types::INTEGER, length: 11, options: ['default' => 0])]
    private int $gamePlanningMonthsdone = 0;

    #[ORM\Column(type: Types::INTEGER, length: 11, options: ['default' => 120])]
    private int $gameEratime = 120;

    #[ORM\Column(type: Types::STRING, length: 128)]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $gameConfigfile = null;

    #[ORM\Column(type: Types::INTEGER, length: 11, options: ['default' => 120])]
    // @phpstan-ignore-next-line int|null but database expects int
    private ?int $gameAutosaveMonthInterval = 120;

    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 0])]
    private int $gameIsRunningUpdate = 0;

    private string $runningGameConfigFileContentsRaw = '';

    private array $runningGameConfigFileContents = [];

    public function getGameId(): ?int
    {
        return $this->gameId;
    }

    public function setGameId(?int $gameId): self
    {
        $this->gameId = $gameId;
        return $this;
    }

    public function getGameStart(): ?int
    {
        return $this->gameStart;
    }

    public function setGameStart(?int $gameStart): self
    {
        $this->gameStart = $gameStart ?? date('Y');
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

    public function getGameLastupdate(): float
    {
        return $this->gameLastupdate;
    }

    public function setGameLastupdate(float $gameLastupdate): self
    {
        $this->gameLastupdate = $gameLastupdate;
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

    public function getGameCurrentMonth(): int
    {
        return $this->gameCurrentmonth;
    }

    public function setGameCurrentMonth(int $gameCurrentmonth): self
    {
        $this->gameCurrentmonth = $gameCurrentmonth;
        return $this;
    }

    public function getGameEnergyupdate(): int
    {
        return $this->gameEnergyupdate;
    }

    public function setGameEnergyupdate(int $gameEnergyupdate): self
    {
        $this->gameEnergyupdate = $gameEnergyupdate;
        return $this;
    }

    public function getGamePlanningGametime(): int
    {
        return $this->gamePlanningGametime;
    }

    public function setGamePlanningGametime(int $gamePlanningGametime): self
    {
        $this->gamePlanningGametime = $gamePlanningGametime;
        return $this;
    }

    public function getGamePlanningRealtime(): int
    {
        return $this->gamePlanningRealtime;
    }

    public function setGamePlanningRealtime(int $gamePlanningRealtime): self
    {
        $this->gamePlanningRealtime = $gamePlanningRealtime;
        return $this;
    }

    public function getGamePlanningEraRealtime(): string
    {
        return $this->gamePlanningEraRealtime;
    }

    public function setGamePlanningEraRealtime(string $gamePlanningEraRealtime): self
    {
        $this->gamePlanningEraRealtime = $gamePlanningEraRealtime;
        return $this;
    }

    public function setGamePlanningEraRealtimeComplete(): self
    {
        $eraRealtimeString = str_repeat($this->gamePlanningRealtime . ",", 4);
        $eraRealtimeString = substr($eraRealtimeString, 0, -1);
        $this->setGamePlanningEraRealtime($eraRealtimeString);
        return $this;
    }

    public function getGamePlanningMonthsdone(): int
    {
        return $this->gamePlanningMonthsdone;
    }

    public function setGamePlanningMonthsdone(int $gamePlanningMonthsdone): self
    {
        $this->gamePlanningMonthsdone = $gamePlanningMonthsdone;
        return $this;
    }

    public function getGameEratime(): int
    {
        return $this->gameEratime;
    }

    public function setGameEratime(int $gameEratime): self
    {
        $this->gameEratime = $gameEratime;
        return $this;
    }

    public function getGameConfigfile(): string
    {
        return $this->gameConfigfile;
    }

    public function setGameConfigfile(string $gameConfigfile): self
    {
        $this->gameConfigfile = $gameConfigfile;
        return $this;
    }

    public function getRunningGameConfigFileContents(): array
    {
        return $this->runningGameConfigFileContents;
    }

    public function setRunningGameConfigFileContents(array $runningGameConfigFileContents): self
    {
        $this->runningGameConfigFileContents = $runningGameConfigFileContents;
        return $this;
    }

    public function getRunningGameConfigFileContentsRaw(): string
    {
        return $this->runningGameConfigFileContentsRaw;
    }

    public function setRunningGameConfigFileContentsRaw(string $runningGameConfigFileContentsRaw): self
    {
        $this->runningGameConfigFileContentsRaw = $runningGameConfigFileContentsRaw;
        return $this;
    }

    public function getGameAutosaveMonthInterval(): int
    {
        return $this->gameAutosaveMonthInterval;
    }

    public function setGameAutosaveMonthInterval(int $gameAutosaveMonthInterval): self
    {
        $this->gameAutosaveMonthInterval = $gameAutosaveMonthInterval;
        return $this;
    }

    public function getGameIsRunningUpdate(): int
    {
        return $this->gameIsRunningUpdate;
    }

    public function setGameIsRunningUpdate(int $gameIsRunningUpdate): self
    {
        $this->gameIsRunningUpdate = $gameIsRunningUpdate;
        return $this;
    }
}
