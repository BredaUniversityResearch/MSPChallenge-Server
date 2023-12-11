<?php

namespace App\Message\Analytics;

use App\Message\Analytics\Helper\AnalyticsDataType;
use DateTimeImmutable;
use JsonSerializable;
use Symfony\Component\Uid\Uuid;

class UserJoinedSession extends AnalyticsMessageBase implements JsonSerializable
{

    public readonly int $userId;
    public readonly string $userName;
    public readonly int $sessionId;
    public readonly int $countryId;

    public function __construct(
        DateTimeImmutable $timeStamp,
        Uuid              $serverManagerId,
        int               $userId,
        string            $userName,
        int               $sessionId,
        int               $countryId,
    ) {
        parent::__construct(
            new AnalyticsDataType(AnalyticsDataType::USER_JOINED_SESSION),
            $timeStamp,
            $serverManagerId
        );
        $this->userId = $userId;
        $this->userName = $userName;
        $this->sessionId = $sessionId;
        $this->countryId = $countryId;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function JsonSerialize() : array
    {
        return [
            'serverManagerId' => $this->serverManagerId,
            'user' =>
            [
                'id' => $this->userId,
                'name' => $this->userName
            ],
            'sessionId' => $this->sessionId,
            'countryId' => $this->countryId
        ];
    }
}
