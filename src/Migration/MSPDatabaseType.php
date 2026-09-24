<?php

namespace App\Migration;

use App\Domain\Common\Enum;

class MSPDatabaseType extends Enum
{
    public const DATABASE_TYPE_SERVER_MANAGER = 'server_manager';
    public const DATABASE_TYPE_GAME_SESSION = 'game_session';
}
