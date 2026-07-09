<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('error' => 'Метод не поддерживается'), JSON_UNESCAPED_UNICODE);
    exit;
}

$coach = isset($_POST['coach']) ? (string) $_POST['coach'] : '';
try {
    $coach = legion_coach_normalize_slug($coach);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
    exit;
}

legion_coach_require_auth_json($coach);

$name = isset($_POST['name']) ? (string) $_POST['name'] : '';
$file = isset($_FILES['photo']) && is_array($_FILES['photo']) ? $_FILES['photo'] : array();

try {
    $data = legion_pilot_upload_athlete_photo($name, $file, $coach);
    $norm = legion_normalize_person_name($name);
    $storedPhoto = '';
    foreach ($data['athletes'] as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (legion_normalize_person_name(isset($row['name']) ? $row['name'] : '') === $norm) {
            $storedPhoto = isset($row['photo']) ? (string) $row['photo'] : '';
            break;
        }
    }

    echo json_encode(array(
        'success' => true,
        'name' => $norm,
        'photo' => legion_pilot_resolve_photo_url($norm, $storedPhoto, $coach),
        'hasPhoto' => legion_pilot_athlete_has_uploaded_photo($storedPhoto, $coach),
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
