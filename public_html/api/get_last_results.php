<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/storage_lib.php';

$scope = storage_validate_scope($_GET['scope'] ?? 'global');
if ($scope === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверный scope']);
    exit;
}

$file = __DIR__ . '/last_results.json';
$all = storage_read_json($file, []);
$data = isset($all[$scope]) && is_array($all[$scope]) ? $all[$scope] : [];

echo json_encode($data, JSON_UNESCAPED_UNICODE);
