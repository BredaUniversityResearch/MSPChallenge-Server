<?php

namespace ServerManager;

/*
UserSpice 5
An Open Source PHP User Management System
by the UserSpice Team at http://UserSpice.com

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
class Session
{

    public static function exists($name): bool
    {
        return isset($_SESSION[$name]);
    }

    public static function put($name, $value)
    {
        return $_SESSION[$name] = $value;
    }

    public static function delete($name): void
    {
        if (self::exists($name)) {
            unset($_SESSION[$name]);
        }
    }

    public static function get($name): ?string
    {
        if (isset($_SESSION[$name])) {
            return $_SESSION[$name];
        }
        return null;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function uagent_no_version(): array|string|null
    {
        $uAgent = $_SERVER['HTTP_USER_AGENT'];
        $regx = '/\/[a-zA-Z0-9.]+/';
        return preg_replace($regx, '', $uAgent);
    }
}
