<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('error' => 'Метод не поддерживается'), JSON_UNESCAPED_UNICODE);
    exit;
}

require_once dirname(__DIR__) . '/pilot_lib.php';

if (!legion_pilot_auth_is_configured()) {
    http_response_code(503);
    echo json_encode(array(
        'error' => 'Не настроен api/pilot_auth.php на сервере. Скопируйте pilot_auth.example.php → pilot_auth.php',
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$password = is_array($payload) && isset($payload['password']) ? (string) $payload['password'] : '';

if (!legion_pilot_verify_password($password)) {
    http_response_code(403);
    echo json_encode(array('error' => 'Неверный пароль'), JSON_UNESCAPED_UNICODE);
    exit;
}

legion_pilot_session_start();
$_SESSION['legion_pilot_auth'] = true;

echo json_encode(array('success' => true), JSON_UNESCAPED_UNICODE);
