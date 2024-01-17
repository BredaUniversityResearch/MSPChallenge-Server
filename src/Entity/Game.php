<?php

namespace App\Entity;

use App\Domain\Common\EntityEnums\GameStateValue;
use App\Repository\GameRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameRepository::class)]

class Game
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, length: 11)]
    private ?int $gameId;

    #[ORM\Column(type: Types::INTEGER, length: 5, nullable: true, options: ['default' => 2010])]
    private ?int $gameStart;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['default' => 'SETUP'])]
    private ?string $gameState;

    #[ORM\Column(type: Types::FLOAT, nullable: true, options: ['default' => 0])]
    private ?float $gameLastupdate;

    #[ORM\Column(type: Types::INTEGER, length: 11, nullable: true, options: ['default' => 1])]
    private ?int $gameCurrentmonth;

    #[ORM\Column(type: Types::SMALLINT, length: 4, nullable: true, options: ['default' => 0])]
    private ?int $gameEnergyupdate;

    #[ORM\Column(type: Types::INTEGER, length: 11, nullable: true, options: ['default' => 36])]
    private ?int $gamePlanningGametime;

    #[ORM\Column(type: Types::INTEGER, length: 11, nullable: true, options: ['default' => 1])]
    private ?int $gamePlanningRealtime;

    #[ORM\Column(type: Types::STRING, length: 256, nullable: true, options: ['default' => 0])]
    private ?string $gamePlanningEraRealtime;

    #[ORM\Column(type: Types::INTEGER, length: 11, nullable: true, options: ['default' => 0])]
    private ?int $gamePlanningMonthsdone;

    #[ORM\Column(type: Types::INTEGER, length: 11, nullable: true, options: ['default' => 120])]
    private ?int $gameEratime;

    #[ORM\Column(type: Types::INTEGER, length: 11, nullable: true, options: ['default' => -1])]
    private ?int $gameMelLastmonth;

    #[ORM\Column(type: Types::INTEGER, length: 11, nullable: true, options: ['default' => -1])]
    private ?int $gameCelLastmonth;

    #[ORM\Column(type: Types::INTEGER, length: 11, nullable: true, options: ['default' => -1])]
    private ?int $gameSelLastmonth;

    #[ORM\Column(type: Types::FLOAT, nullable: true, options: ['default' => 0])]
    private ?float $gameMelLastupdate;

    #[ORM\Column(type: Types::FLOAT, nullable: true, options: ['default' => 0])]
    private ?float $gameCelLastupdate;

    #[ORM\Column(type: Types::FLOAT, nullable: true, options: ['default' => 0])]
    private ?float $gameSelLastupdate;

    #[ORM\Column(type: Types::STRING, length: 128, nullable: true)]
    private ?string $gameConfigfile;

    #[ORM\Column(type: Types::INTEGER, length: 11, nullable: true, options: ['default' => 120])]
    private ?int $gameAutosaveMonthInterval;

    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 0])]
    private ?int $gameIsRunningUpdate;

    public function getGameId(): ?int
    {
        return $this->gameId;
    }

    public function setGameId(?int $gameId): Game
    {
        $this->gameId = $gameId;
        return $this;
    }

    public function getGameStart(): ?int
    {
        return $this->gameStart;
    }

    public function setGameStart(?int $gameStart): Game
    {
        $this->gameStart = $gameStart ?? date('Y');
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

    public function getGameLastupdate(): ?float
    {
        return $this->gameLastupdate;
    }

    public function setGameLastupdate(?float $gameLastupdate): Game
    {
        $this->gameLastupdate = $gameLastupdate;
        return $this;
    }

    public function getGameCurrentmonth(): ?int
    {
        return $this->gameCurrentmonth;
    }

    public function setGameCurrentmonth(?int $gameCurrentmonth): Game
    {
        $this->gameCurrentmonth = $gameCurrentmonth;
        return $this;
    }

    public function getGameEnergyupdate(): ?int
    {
        return $this->gameEnergyupdate;
    }

    public function setGameEnergyupdate(?int $gameEnergyupdate): Game
    {
        $this->gameEnergyupdate = $gameEnergyupdate;
        return $this;
    }

    public function getGamePlanningGametime(): ?int
    {
        return $this->gamePlanningGametime;
    }

    public function setGamePlanningGametime(?int $gamePlanningGametime): Game
    {
        $this->gamePlanningGametime = $gamePlanningGametime;
        return $this;
    }

    public function getGamePlanningRealtime(): ?int
    {
        return $this->gamePlanningRealtime;
    }

    public function setGamePlanningRealtime(?int $gamePlanningRealtime): Game
    {
        $this->gamePlanningRealtime = $gamePlanningRealtime;
        return $this;
    }

    public function getGamePlanningEraRealtime(): ?string
    {
        return $this->gamePlanningEraRealtime;
    }

    public function setGamePlanningEraRealtime(?string $gamePlanningEraRealtime): Game
    {
        $this->gamePlanningEraRealtime = $gamePlanningEraRealtime;
        return $this;
    }

    public function setGamePlanningEraRealtimeComplete(): Game
    {
        $eraRealtimeString = str_repeat($this->gamePlanningRealtime . ",", 4);
        $eraRealtimeString = substr($eraRealtimeString, 0, -1);
        $this->setGamePlanningEraRealtime($eraRealtimeString);
        return $this;
    }

    public function getGamePlanningMonthsdone(): ?int
    {
        return $this->gamePlanningMonthsdone;
    }

    public function setGamePlanningMonthsdone(?int $gamePlanningMonthsdone): Game
    {
        $this->gamePlanningMonthsdone = $gamePlanningMonthsdone;
        return $this;
    }

    public function getGameEratime(): ?int
    {
        return $this->gameEratime;
    }

    public function setGameEratime(?int $gameEratime): Game
    {
        $this->gameEratime = $gameEratime;
        return $this;
    }

    public function getGameMelLastmonth(): ?int
    {
        return $this->gameMelLastmonth;
    }

    public function setGameMelLastmonth(?int $gameMelLastmonth): Game
    {
        $this->gameMelLastmonth = $gameMelLastmonth;
        return $this;
    }

    public function getGameCelLastmonth(): ?int
    {
        return $this->gameCelLastmonth;
    }

    public function setGameCelLastmonth(?int $gameCelLastmonth): Game
    {
        $this->gameCelLastmonth = $gameCelLastmonth;
        return $this;
    }

    public function getGameSelLastmonth(): ?int
    {
        return $this->gameSelLastmonth;
    }

    public function setGameSelLastmonth(?int $gameSelLastmonth): Game
    {
        $this->gameSelLastmonth = $gameSelLastmonth;
        return $this;
    }

    public function getGameMelLastupdate(): ?float
    {
        return $this->gameMelLastupdate;
    }

    public function setGameMelLastupdate(?float $gameMelLastupdate): Game
    {
        $this->gameMelLastupdate = $gameMelLastupdate;
        return $this;
    }

    public function getGameCelLastupdate(): ?float
    {
        return $this->gameCelLastupdate;
    }

    public function setGameCelLastupdate(?float $gameCelLastupdate): Game
    {
        $this->gameCelLastupdate = $gameCelLastupdate;
        return $this;
    }

    public function getGameSelLastupdate(): ?float
    {
        return $this->gameSelLastupdate;
    }

    public function setGameSelLastupdate(?float $gameSelLastupdate): Game
    {
        $this->gameSelLastupdate = $gameSelLastupdate;
        return $this;
    }

    public function getGameConfigfile(): ?string
    {
        return $this->gameConfigfile;
    }

    public function setGameConfigfile(?string $gameConfigfile): Game
    {
        $this->gameConfigfile = $gameConfigfile;
        return $this;
    }

    public function getGameAutosaveMonthInterval(): ?int
    {
        return $this->gameAutosaveMonthInterval;
    }

    public function setGameAutosaveMonthInterval(?int $gameAutosaveMonthInterval): Game
    {
        $this->gameAutosaveMonthInterval = $gameAutosaveMonthInterval;
        return $this;
    }

    public function getGameIsRunningUpdate(): ?int
    {
        return $this->gameIsRunningUpdate;
    }

    public function setGameIsRunningUpdate(?int $gameIsRunningUpdate): Game
    {
        $this->gameIsRunningUpdate = $gameIsRunningUpdate;
        return $this;
    }
}
