<?php

namespace App\Notifications;

interface NotificationInterface {

    public static function send(int $code): void;
}
