<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/club_storage_lib.php';

$scope = storage_validate_scope($_GET['scope'] ?? 'global');
if ($scope === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверный scope']);
    exit;
}

echo json_encode(legion_club_load_scope_achievements($scope), JSON_UNESCAPED_UNICODE);
