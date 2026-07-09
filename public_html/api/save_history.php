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
require_once __DIR__ . '/history_lib.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['entries']) || !is_array($data['entries'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверный формат данных']);
    exit;
}

$historyFile = __DIR__ . '/history.json';
$existing = storage_read_json($historyFile, []);
$existing = array_merge($existing, $data['entries']);
$existing = legion_trim_history_per_athlete($existing);

if (!storage_write_json($historyFile, $existing)) {
    http_response_code(500);
    echo json_encode(['error' => 'Не удалось сохранить историю']);
    exit;
}

echo json_encode(['success' => true, 'count' => count($data['entries'])]);
