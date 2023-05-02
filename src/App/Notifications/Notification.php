<?php

namespace App\Notifications;

class Notification
{
    public static function send($user, string $type, int $code)
    {
        switch ($type) {
            case 'sms':
                Sms::send($user, $code);
                break;
            case 'email':
                Email::send($user, $code);
                break;
            case 'telegram':
                Telegram::send($user, $code);
        }
    }
}
