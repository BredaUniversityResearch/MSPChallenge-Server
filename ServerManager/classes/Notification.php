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

class Notification
{
    private function getAllNotifications($all): bool
    {
        return false;
    }

    public function archiveOldNotifications($user_id): bool
    {
        return false;
    }

    public function addNotification($message, $user_id = -1): bool
    {
        return false;
    }

    public function setRead($notification_id, $read = true): bool
    {
        return false;
    }

    public function setReadAll($read = true): bool
    {
        return false;
    }

    public function getError(): bool
    {
        return false;
    }

    public function getNotifications(): bool
    {
        return false;
    }

    public function getCount(): bool
    {
        return false;
    }

    public function getUnreadCount(): bool
    {
        return false;
    }

    public function getLiveUnreadCount(): bool
    {
        return false;
    }

    public function getUnreadNotifications(): bool
    {
        return false;
    }
}
