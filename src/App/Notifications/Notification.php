<?php

namespace App\Notifications;

class Notification
{
    public static function send(string $type, int $code)
    {
        switch ($type) {
            case 'sms':
                Sms::send($code);
                break;
            case 'email':
                Email::send($code);
                break;
            case 'telegram':
                Telegram::send($code);
        }
    }
}
