<?php

namespace App\Notification;

interface NotificationInterface {

    public static function send(int $code): void;
}
