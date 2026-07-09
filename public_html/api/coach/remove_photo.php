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

try {
    $data = legion_pilot_remove_athlete_photo($name, $coach);
    $norm = legion_normalize_person_name($name);
    echo json_encode(array(
        'success' => true,
        'name' => $norm,
        'photo' => legion_pilot_resolve_photo_url($norm, '', $coach),
        'hasPhoto' => false,
        'avatarIndex' => legion_pilot_default_avatar_index($norm),
        'updatedAt' => isset($data['updatedAt']) ? $data['updatedAt'] : '',
    ), JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
}
