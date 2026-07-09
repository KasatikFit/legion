<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('error' => 'Метод не поддерживается'), JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$coach = is_array($payload) && isset($payload['coach']) ? (string) $payload['coach'] : '';
try {
    $coach = legion_coach_normalize_slug($coach);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
    exit;
}

legion_coach_require_auth_json($coach);
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(array('error' => 'Некорректный JSON'), JSON_UNESCAPED_UNICODE);
    exit;
}

$resultsUrl = isset($payload['resultsUrl']) ? trim((string) $payload['resultsUrl']) : '';
$ranksUrl = isset($payload['ranksUrl']) ? trim((string) $payload['ranksUrl']) : '';
$keepHistory = !isset($payload['keepHistory']) || !empty($payload['keepHistory']);

try {
    $result = legion_pilot_import_from_sheets($resultsUrl, $ranksUrl, $keepHistory, $coach);
    $result['ok'] = true;
    $result['storage'] = function_exists('legion_pilot_db_storage_label') && legion_pilot_db_enabled()
        ? legion_pilot_db_storage_label()
        : 'json';
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
}
