<?php

namespace App\Message\Analytics;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;
use App\Message\Analytics\Helper\AnalyticsDataType;

abstract class AnalyticsMessageBase
{
    public readonly AnalyticsDataType $type;
    public readonly DateTimeImmutable $timeStamp;
    public readonly Uuid $serverManagerId;

    public function __construct(
        AnalyticsDataType $type,
        DateTimeImmutable $timeStamp,
        Uuid $serverManagerId
    ) {
        $this->type = $type;
        $this->timeStamp = $timeStamp;
        $this->serverManagerId = $serverManagerId;
    }
}
