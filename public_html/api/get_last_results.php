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

$data = legion_club_load_snapshot($scope, 'results');

$merge = isset($_GET['merge']) && ($_GET['merge'] === '1' || $_GET['merge'] === 'true');
if ($merge && $scope === 'global' && count($data) === 0 && !legion_club_storage_enabled()) {
    $file = __DIR__ . '/last_results.json';
    $all = storage_read_json($file, array());
    $data = storage_merge_last_results($all);
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
