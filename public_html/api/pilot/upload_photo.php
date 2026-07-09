<?php

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/pilot_lib.php';

legion_pilot_require_auth_json();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('error' => 'Метод не поддерживается'), JSON_UNESCAPED_UNICODE);
    exit;
}

$name = isset($_POST['name']) ? (string) $_POST['name'] : '';
$file = isset($_FILES['photo']) && is_array($_FILES['photo']) ? $_FILES['photo'] : array();

try {
    $data = legion_pilot_upload_athlete_photo($name, $file);
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
        'photo' => legion_pilot_resolve_photo_url($norm, $storedPhoto),
        'hasPhoto' => legion_pilot_athlete_has_uploaded_photo($storedPhoto),
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
