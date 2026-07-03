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

require_once __DIR__ . '/storage_lib.php';

$input = file_get_contents('php://input');
$payload = json_decode($input, true);

if (!$payload || !isset($payload['scope']) || !isset($payload['data']) || !is_array($payload['data'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверный формат данных']);
    exit;
}

$scope = storage_validate_scope($payload['scope']);
if ($scope === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверный scope']);
    exit;
}

$file = __DIR__ . '/achievements.json';
$all = storage_read_json($file, []);
$all[$scope] = $payload['data'];

if (!storage_write_json($file, $all)) {
    http_response_code(500);
    echo json_encode(['error' => 'Не удалось сохранить данные']);
    exit;
}

echo json_encode(['success' => true]);
