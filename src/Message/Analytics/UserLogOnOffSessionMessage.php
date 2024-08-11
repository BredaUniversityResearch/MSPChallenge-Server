<?php

namespace App\Message\Analytics;

use App\Message\Analytics\Helper\AnalyticsDataType;
use DateTimeImmutable;
use JsonSerializable;
use Symfony\Component\Uid\Uuid;

class UserLogOnOffSessionMessage extends AnalyticsMessageBase
{

    public readonly int $sessionId;
    public readonly int $gameSessionId;
    public readonly int $countryId;

    public function __construct(
        bool $logOn,
        DateTimeImmutable $timeStamp,
        Uuid $serverManagerId,
        int $sessionId,
        int $gameSessionId,
        int $countryId,
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
        $this->sessionId = $sessionId;
        $this->gameSessionId = $gameSessionId;
        $this->countryId = $countryId;
    }
}
