<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('error' => 'Метод не поддерживается'), JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/_lib.php';

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(array('error' => 'Неверный JSON'), JSON_UNESCAPED_UNICODE);
    exit;
}

$coach = isset($payload['coach']) ? (string) $payload['coach'] : '';
try {
    $coach = legion_coach_normalize_slug($coach);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
    exit;
}

legion_coach_require_auth_json($coach);

$name = isset($payload['name']) ? (string) $payload['name'] : '';
$athleteId = legion_pilot_payload_athlete_id($payload);
$birthdate = isset($payload['birthdate']) ? $payload['birthdate'] : '';

try {
    $data = legion_pilot_update_birthdate($name, $birthdate, $coach, $athleteId);
    $resolved = legion_pilot_resolve_athlete($data['athletes'], $athleteId, $name);
    $row = $resolved['athlete'];
    $normalized = isset($row['birthdate']) ? $row['birthdate'] : null;
    $age = legion_pilot_compute_age($normalized);
    echo json_encode(array(
        'success' => true,
        'updatedAt' => $data['updatedAt'],
        'birthdate' => $normalized,
        'age' => $age,
        'athleteId' => isset($row['id']) ? (int) $row['id'] : null,
        'name' => isset($row['name']) ? $row['name'] : $name,
    ), JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
}
