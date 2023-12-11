<?php

namespace App\Message\Analytics\Helper;

use DateTimeImmutable;
use JsonSerializable;

readonly class GameSession implements JsonSerializable
{

    public int $id;
    public string $name;
    public DateTimeImmutable $creationTime;
    public DateTimeImmutable $runningTillTime;
    public int $startYear;
    public int $endMonth;
    public int $currentMonth;
    public string $visibility;

    public function __construct(
        int $id,
        string $name,
        DateTimeImmutable $creationTime,
        DateTimeImmutable $runningTillTime,
        int $startYear,
        int $endMonth,
        int $currentMonth,
        string $visibility
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->creationTime = $creationTime;
        $this->runningTillTime = $runningTillTime;
        $this->startYear = $startYear;
        $this->endMonth = $endMonth;
        $this->currentMonth =$currentMonth;
        $this->visibility = $visibility;
    }

    //phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function JsonSerialize() : array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'creationTime' => $this->creationTime->format('c'),
            'runningTillTime' => $this->runningTillTime->format('c'),
            'startYear' => $this->startYear,
            'endMonth' => $this->endMonth,
            'currentMonth' => $this->currentMonth,
            'visibility' => $this->visibility
        ];
    }
}
