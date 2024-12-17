<?php

namespace App\Message\Watchdog;

class GameStateChangedMessage extends WatchdogMessageBase
{
    private string $gameSessionApi;
    private string $gameState;
    private array $requiredSimulations;
    private Token $apiAccessToken;
    private Token $apiAccessRenewToken;
    private int $month;

    public function getGameSessionApi(): string
    {
        return $this->gameSessionApi;
    }

    public function setGameSessionApi(string $gameSessionApi): self
    {
        $this->gameSessionApi = $gameSessionApi;
        return $this;
    }

    public function getGameState(): string
    {
        return $this->gameState;
    }

    public function setGameState(string $gameState): self
    {
        $this->gameState = $gameState;
        return $this;
    }

    public function getRequiredSimulations(): array
    {
        return $this->requiredSimulations;
    }

    public function setRequiredSimulations(array $requiredSimulations): self
    {
        $this->requiredSimulations = $requiredSimulations;
        return $this;
    }

    public function getApiAccessToken(): Token
    {
        return $this->apiAccessToken;
    }

    public function setApiAccessToken(Token $apiAccessToken): self
    {
        $this->apiAccessToken = $apiAccessToken;
        return $this;
    }

    public function getApiAccessRenewToken(): Token
    {
        return $this->apiAccessRenewToken;
    }

    public function setApiAccessRenewToken(Token $apiAccessRenewToken): self
    {
        $this->apiAccessRenewToken = $apiAccessRenewToken;
        return $this;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    public function setMonth(int $month): self
    {
        $this->month = $month;
        return $this;
    }
}
