<?php

namespace App\Message\Analytics;

use App\Message\Analytics\Helper\AnalyticsDataType;
use App\Message\Analytics\Helper\GameConfig;
use App\Message\Analytics\Helper\GameSession;
use DateTimeImmutable;
use JsonSerializable;
use Symfony\Component\Uid\Uuid;

class SessionCreated extends AnalyticsMessageBase implements JsonSerializable
{
    public readonly GameSession $session;
    public readonly GameConfig $config;
    public readonly string $userName;
    public readonly int $accountId;

    public function __construct(
        DateTimeImmutable $timeStamp,
        Uuid              $serverManagerId,
        string            $userName,
        int               $accountId,
        int               $sessionId,
        string            $sessionName,
        DateTimeImmutable $gameCreationTime,
        DateTimeImmutable $gameRunningTillTime,
        int               $gameStartYear,
        int               $gameEndMonth,
        int               $gameCurrentMonth,
        string            $gameVisibility,
        string            $configFileName,
        string            $configFilePath,
        string            $configVersion,
        string            $configVersionMessage,
        string            $configVisibility,
        string            $configRegion,
        string            $configDescription,
        string            $configUploadUserName,
        int               $configUploadUserAccountId,
        DateTimeImmutable $configUploadTime
    ) {
        parent::__construct(
            new AnalyticsDataType(AnalyticsDataType::GAME_SESSION_CREATED),
            $timeStamp,
            $serverManagerId
        );
        $this->userName = $userName;
        $this->accountId = $accountId;
        $this->session = new GameSession(
            $sessionId,
            $sessionName,
            $gameCreationTime,
            $gameRunningTillTime,
            $gameStartYear,
            $gameEndMonth,
            $gameCurrentMonth,
            $gameVisibility
        );
        $this->config = new GameConfig(
            $configFileName,
            $configFilePath,
            $configVersion,
            $configVersionMessage,
            $configVisibility,
            $configRegion,
            $configDescription,
            $configUploadUserName,
            $configUploadUserAccountId,
            $configUploadTime
        );
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function JsonSerialize() : array
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
