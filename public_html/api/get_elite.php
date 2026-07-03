<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/storage_lib.php';

$scope = storage_validate_scope($_GET['scope'] ?? '', false);
if ($scope === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверный scope']);
    exit;
}

$file = __DIR__ . '/elite.json';
$all = storage_read_json($file, []);
$scopeData = isset($all[$scope]) && is_array($all[$scope]) ? $all[$scope] : [];

$result = [
    'elite' => isset($scopeData['elite']) && is_array($scopeData['elite']) ? $scopeData['elite'] : [],
    'lastRotationMonth' => isset($scopeData['lastRotationMonth']) ? $scopeData['lastRotationMonth'] : null
];

echo json_encode($result, JSON_UNESCAPED_UNICODE);
