<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не поддерживается']);
    exit;
}

$configFile = __DIR__ . '/rotation_config.php';
if (!file_exists($configFile)) {
    http_response_code(503);
    echo json_encode(['error' => 'Пароль ротации не настроен', 'valid' => false]);
    exit;
}

require_once $configFile;

$input = file_get_contents('php://input');
$payload = json_decode($input, true);
$password = isset($payload['password']) ? (string) $payload['password'] : '';

$valid = defined('ROTATION_PASSWORD') && hash_equals(ROTATION_PASSWORD, $password);

echo json_encode(['valid' => $valid]);
