<?php

namespace App\Message\Analytics;

use DateTimeImmutable;

abstract class AnalyticsMessageBase
{
    public readonly DateTimeImmutable $timeStamp;

    public function __construct(
        DateTimeImmutable $timeStamp
    ) {
        $this->timeStamp = $timeStamp;
    }
}
