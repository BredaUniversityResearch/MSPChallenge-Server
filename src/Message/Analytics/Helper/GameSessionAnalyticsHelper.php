<?php

namespace App\Message\Analytics\Helper;

use DateTimeImmutable;
use JsonSerializable;

class GameSessionAnalyticsHelper implements JsonSerializable
{

    public int $id;
    public string $name;
    public DateTimeImmutable $creationTime;
    public int $startYear;
    public int $endMonth;

    public function __construct(
        int $id,
        string $name,
        DateTimeImmutable $creationTime,
        int $startYear,
        int $endMonth,
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->creationTime = $creationTime;
        $this->startYear = $startYear;
        $this->endMonth = $endMonth;
    }

    public function jsonSerialize() : array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'creationTime' => $this->creationTime->format('c'),
            'startYear' => $this->startYear,
            'endMonth' => $this->endMonth,
        ];
    }
}
