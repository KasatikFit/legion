<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/admin_auth_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('error' => 'Метод не поддерживается'), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!legion_admin_auth_is_configured()) {
    http_response_code(503);
    echo json_encode(array(
        'error' => 'Суперадмин не настроен. Скопируйте api/admin_auth.example.php → api/admin_auth.php',
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$password = is_array($payload) && isset($payload['password']) ? (string) $payload['password'] : '';

if (!legion_admin_verify_password($password)) {
    http_response_code(401);
    echo json_encode(array('error' => 'Неверный пароль'), JSON_UNESCAPED_UNICODE);
    exit;
}

legion_admin_set_authenticated(true);
echo json_encode(array('success' => true), JSON_UNESCAPED_UNICODE);
