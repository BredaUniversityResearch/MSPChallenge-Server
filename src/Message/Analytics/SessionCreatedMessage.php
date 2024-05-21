<?php

namespace App\Message\Analytics;

use App\Message\Analytics\Helper\AnalyticsDataType;
use App\Message\Analytics\Helper\GameConfigAnalyticsHelper;
use App\Message\Analytics\Helper\GameSessionAnalyticsHelper;
use DateTimeImmutable;
use JsonSerializable;
use Symfony\Component\Uid\Uuid;

class SessionCreatedMessage extends AnalyticsMessageBase implements JsonSerializable
{
    public readonly GameSessionAnalyticsHelper $session;
    public readonly GameConfigAnalyticsHelper $config;
    public readonly string $userName;
    public readonly int $accountId;

    public function __construct(
        DateTimeImmutable $timeStamp,
        Uuid $serverManagerId,
        string $userName,
        int $accountId,
        int $sessionId,
        string $sessionName,
        DateTimeImmutable $gameCreationTime,
        int $gameStartYear,
        int $gameEndMonth,
        string $configFileName,
        string $configVersion,
        string $configVersionMessage,
        string $configRegion,
        string $configDescription
    ) {
        parent::__construct(
            new AnalyticsDataType(AnalyticsDataType::GAME_SESSION_CREATED),
            $timeStamp,
            $serverManagerId
        );
        $this->userName = $userName;
        $this->accountId = $accountId;
        $this->session = new GameSessionAnalyticsHelper(
            $sessionId,
            $sessionName,
            $gameCreationTime,
            $gameStartYear,
            $gameEndMonth
        );
        $this->config = new GameConfigAnalyticsHelper(
            $configFileName,
            $configVersion,
            $configVersionMessage,
            $configRegion,
            $configDescription
        );
    }

    public function jsonSerialize() : array
    {
        return [
            'serverManagerId' => $this->serverManagerId,
            'user' => [
                'name' => $this->userName,
                'accountId' => $this->accountId
            ],
            'session' => $this->session,
            'config' => $this->config
        ];
    }
}
