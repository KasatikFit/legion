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

require_once __DIR__ . '/club_storage_lib.php';

$input = file_get_contents('php://input');
$payload = json_decode($input, true);

if (!$payload || !isset($payload['scope']) || !isset($payload['elite']) || !is_array($payload['elite'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверный формат данных']);
    exit;
}

$scope = storage_validate_scope($payload['scope'], false);
if ($scope === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверный scope']);
    exit;
}

try {
    legion_club_save_elite(
        $scope,
        array_values($payload['elite']),
        isset($payload['lastRotationMonth']) ? $payload['lastRotationMonth'] : null
    );
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
