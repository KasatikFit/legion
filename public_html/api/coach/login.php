<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('error' => 'Метод не поддерживается'), JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/_lib.php';

$payload = json_decode(file_get_contents('php://input'), true);
$coach = is_array($payload) && isset($payload['coach']) ? (string) $payload['coach'] : '';
$password = is_array($payload) && isset($payload['password']) ? (string) $payload['password'] : '';

try {
    $coach = legion_coach_normalize_slug($coach);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!legion_coach_auth_is_configured($coach)) {
    http_response_code(503);
    echo json_encode(array(
        'error' => 'Не настроен пароль. Скопируйте api/coach_auth.example.php → api/coach_auth.php',
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!legion_coach_verify_password($coach, $password)) {
    http_response_code(403);
    echo json_encode(array('error' => 'Неверный пароль'), JSON_UNESCAPED_UNICODE);
    exit;
}

legion_coach_set_authenticated($coach, true);
echo json_encode(array('success' => true, 'coach' => $coach), JSON_UNESCAPED_UNICODE);
