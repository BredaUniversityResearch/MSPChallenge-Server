<?php

namespace App\Message\Analytics;

use DateTimeImmutable;
use JsonSerializable;

class ClientJoinedSession extends AnalyticsMessageBase implements JsonSerializable
{

    public readonly int $id;
    public string $user_name;
    public int $account_id;
    public int $session_id;

    public function __construct(
        DateTimeImmutable $timeStamp,
        int $id,
        string $user_name,
        int $account_id,
        int $session_id
    ) {
        parent::__construct($timeStamp);
        $this->id = $id;
        $this->user_name = $user_name;
        $this->account_id = $account_id;
        $this->session_id = $session_id;
    }

    public function JsonSerialize() : array
    {
        return [
            'id' => $this->id,
            'user_name' => $this->user_name,
            'account_id' => $this->account_id,
            'session_id' => $this->session_id
        ];
    }

}