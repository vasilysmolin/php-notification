<?php

namespace Framework;

use Dotenv\Dotenv;
use PDO;

class Jwt
{
    private $token = null;

    public function __construct(string $bearer)
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
        $dotenv->load();
        $token = explode('Bearer ', $bearer);
        if (isset($token[1])) {
            $this->token = $token[1];
        }
    }

//    function createToken($userId, $secret) {
//        // заголовок
//        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
//        // содержимое
//        $payload = json_encode(['sub' => $userId, 'iat' => time()]);
//        // подпись
//        $signature = hash_hmac('sha256', "$header.$payload", $secret, true);
//        $signature = base64_encode($signature);
//        // полный токен
//        $token = "$header.$payload.$signature";
//        return $token;
//    }


    public function verifyToken(): bool {

        list($header, $payload, $signature) = explode('.', $this->token);
        $valid = hash_hmac('sha256', "$header.$payload", $_ENV['JWT_SECRET'], true);
        $valid = base64_encode($valid);
        return $signature === substr($valid,0,-1);
    }

    public function getUser(): int {

        list($header, $payload, $signature) = explode('.', $this->token);
        $payload = json_decode(base64_decode($payload), true);
        return $payload['userID'];
    }

}
