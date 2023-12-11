<?php

namespace App\Message\Analytics\Helper;

use App\Domain\Common\Enum;

class AnalyticsDataType extends Enum
{

    public const GAME_SESSION_CREATED = "GameSessionCreated";
    public const GAME_SESSION_ARCHIVED = "GameSessionArchived";
    public const USER_JOINED_SESSION = "UserJoinedSession";
    public const USER_LEFT_SESSION = "UserLeftSession";
}
