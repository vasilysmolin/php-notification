<?php

use App\Notifications\Notification;
use Framework\Connection;

chdir(dirname(__DIR__));
require 'vendor/autoload.php';

try {
    $conn = Connection::getInstance();
} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
}
header('Content-Type: application/json');
$path = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

if ($path === '/users' && $method === 'GET') {
    $users = $conn->query('select * from users')->fetchAll();
    echo json_encode($users);
} elseif (preg_match('#^/users/(?P<id>\d+)$#i', $path, $matches)  && $method === 'GET') {
    $query = $conn->prepare("SELECT * FROM users WHERE `id` = ?");
    $query->execute([$matches['id']]);
    $user = $query->fetch();
    echo json_encode($user);
} elseif ($path === '/users'  && $method === 'POST') {
    $json = json_decode(file_get_contents('php://input'), true);
    $query = "INSERT INTO users (email, phone, password, created_at, updated_at) VALUES (:email, :phone, :password, :created_at, :updated_at)";
    $params = [
        ':email' => $json['email'],
        ':phone' => $json['phone'],
        ':password' => md5($json['password']),
        ':created_at' => date("Y-m-d H:i:s"),
        ':updated_at' => date("Y-m-d H:i:s"),
    ];

    $query = $conn->prepare($query);
    $query->execute($params);

    http_response_code(201);
    echo json_encode([]);
} elseif (preg_match('#^/settings/(?P<id>\d+)$#i', $path, $matches)  && $method === 'GET') {
    $query = $conn->prepare("SELECT * FROM user_settings WHERE `user_id` = ?");
    $query->execute([$matches['id']]);
    $setting = $query->fetch();
    echo json_encode($setting);
} elseif (preg_match('#^/settings/(?P<id>\d+)$#i', $path, $matches) && $method === 'PUT') {
    $json = json_decode(file_get_contents('php://input'), true);
    $query = $conn->prepare("SELECT * FROM users WHERE `email` = ?");
    $query->execute([$json['email']]);
    $user = $query->fetch();
    $code = base64_encode($json['code']);
    $query = $conn->prepare("SELECT * FROM user_code WHERE `code` = ? AND `user_id` = ? ORDER BY id DESC");
    $query->execute([$code, $user['id']]);
    $checkCode = $query->fetch();
    if (empty($checkCode)) {
        http_response_code(422);
        echo json_encode(['error' => 'Неверный код подтверждения']);
        return;
    }
    $newTime = strtotime('-3 minutes');
    if ($checkCode['created_at'] < date("Y-m-d H:i:s", $newTime)) {
        http_response_code(422);
        echo json_encode(['error' => 'Истекло время кода подтверждения']);
        return;
    }
    $query = "UPDATE `user_settings` SET `confirmation` = :confirmation WHERE `id` = :id";
    $params = [
        ':id' => $user['id'],
        ':confirmation' => $json['confirmation']
    ];
    $query = $conn->prepare($query);
    $query->execute($params);
    http_response_code(204);
    echo json_encode([]);
} elseif (preg_match('#^/send-code/(?P<id>\d+)$#i', $path, $matches)  && $method === 'POST') {
    $json = json_decode(file_get_contents('php://input'), true);
    $query = $conn->prepare("SELECT * FROM users WHERE `email` = ?");
    $query->execute([$json['email']]);
    $user = $query->fetch();

    $query = $conn->prepare("SELECT COUNT(*) as count FROM user_code WHERE date(`created_at`) = ? and `user_id` = ? ORDER BY id DESC ");
    $query->execute([date("Y-m-d"), $user['id']]);
    $setting = $query->fetch();
    if ($setting['count'] > 3) {
        http_response_code(422);
        echo json_encode(['error' => 'Превышен лимит подтверждений']);
        return;
    }
    $query = $conn->prepare("SELECT * FROM user_settings WHERE `user_id` = ?");
    $query->execute([$user['id']]);
    $setting = $query->fetch();

    $rand = rand(1111, 9999);
    $code = base64_encode($rand);
    Notification::send($user, $setting['confirmation'], $rand);

    $query = "INSERT INTO user_code (user_id, code, created_at, updated_at) VALUES (:user_id, :code, :created_at, :updated_at)";
    $params = [
        ':user_id' => $user['id'],
        ':code' => $code,
        ':created_at' => date("Y-m-d H:i:s"),
        ':updated_at' => date("Y-m-d H:i:s"),
    ];

    $query = $conn->prepare($query);
    $query->execute($params);

    http_response_code(201);
    echo json_encode([]);
}
