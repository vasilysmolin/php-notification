<?php

namespace App\Notifications;

class Sms implements NotificationInterface
{
    public static function send($user, int $code): void
    {
    }
}
