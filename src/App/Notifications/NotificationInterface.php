<?php

namespace App\Notifications;

interface NotificationInterface
{
    public static function send($user, int $code): void;
}
