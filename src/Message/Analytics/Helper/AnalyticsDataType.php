<?php

namespace App\Message\Analytics\Helper;

use App\Domain\Common\Enum;

class AnalyticsDataType extends Enum
{

    public const GAME_SESSION_CREATED = "GameSessionCreated";
    public const GAME_SESSION_ARCHIVED = "GameSessionArchived";
    public const USER_LOGON_SESSION = "UserLoggedOnSession";
    public const USER_LOGOFF_SESSION = "UserLoggedOffSession";
}
