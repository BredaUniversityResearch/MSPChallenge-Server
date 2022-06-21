<?php

namespace App\Domain\Common;

class DatabaseDefaults
{
    const DEFAULT_DATABASE_HOST = 'localhost';
    const DEFAULT_DATABASE_PORT = 3306;
    const DEFAULT_DATABASE_USER = 'root';
    const DEFAULT_DATABASE_PASSWORD = '';
    const DEFAULT_DATABASE_SERVER_VERSION = 'mariadb-10.4.22';
    const DEFAULT_DATABASE_CHARSET = 'utf8mb4';
    const DEFAULT_DATABASE_CREATOR_USER = self::DEFAULT_DATABASE_USER;
    const DEFAULT_DATABASE_CREATOR_PASSWORD = self::DEFAULT_DATABASE_PASSWORD;

    const DEFAULT_DBNAME_SESSION_PREFIX = 'msp_session_';
    const DEFAULT_DBNAME_SERVER_MANAGER = 'msp_server_manager';
}
