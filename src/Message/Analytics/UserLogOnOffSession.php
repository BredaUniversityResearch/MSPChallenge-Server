<?php

namespace App\Message\Analytics;

use App\Message\Analytics\Helper\AnalyticsDataType;
use DateTimeImmutable;
use JsonSerializable;
use Symfony\Component\Uid\Uuid;

class UserLogOnOffSession extends AnalyticsMessageBase implements JsonSerializable
{

    public readonly int $userId;
    public readonly string $userName;
    public readonly int $gameSessionId;
    public readonly int $countryId;

    public function __construct(
        bool              $logOn,
        DateTimeImmutable $timeStamp,
        Uuid              $serverManagerId,
        int               $userId,
        string            $userName,
        int               $gameSessionId,
        int               $countryId,
    ) {
        parent::__construct(
            new AnalyticsDataType(
                $logOn ?
                    AnalyticsDataType::USER_LOGON_SESSION :
                    AnalyticsDataType::USER_LOGOFF_SESSION
            ),
            $timeStamp,
            $serverManagerId
        );
        $this->userId = $userId;
        $this->userName = $userName;
        $this->gameSessionId = $gameSessionId;
        $this->countryId = $countryId;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function JsonSerialize() : array
    {
        $result = [
            'serverManagerId' => $this->serverManagerId,
            'user' =>
            [
                'id' => $this->userId,
                'name' => $this->userName
            ],
            'gameSessionId' => $this->gameSessionId
        ];

        if ($this->type == AnalyticsDataType::USER_LOGON_SESSION) {
            $result['countryId'] = $this->countryId;
        }
        return $result;
    }
}
