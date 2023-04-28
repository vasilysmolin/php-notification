<?php

namespace App\Notification;

class Notificaion {

    public static function send(string $type, int $code)
    {
        switch ($type) {
            case 'sms':
                Sms::send($code);
            case 'email':
                Email::send($code);
            case 'telegram':
                Telegram::send($code);
        }
    }
}
