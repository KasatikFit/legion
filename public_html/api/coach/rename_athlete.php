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
try {
    $coach = legion_coach_normalize_slug($coach);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
    exit;
}

legion_coach_require_auth_json($coach);

$name = is_array($payload) && isset($payload['name']) ? (string) $payload['name'] : '';
$newName = is_array($payload) && isset($payload['newName']) ? (string) $payload['newName'] : '';
$athleteId = is_array($payload) ? legion_pilot_payload_athlete_id($payload) : 0;

try {
    $data = legion_pilot_rename_athlete($name, $newName, $coach, $athleteId);
    echo json_encode(array(
        'success' => true,
        'updatedAt' => $data['updatedAt'],
        'oldName' => $data['oldName'],
        'name' => $data['name'],
        'athleteId' => isset($data['athleteId']) ? $data['athleteId'] : null,
    ), JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
}
