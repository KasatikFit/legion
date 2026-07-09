<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('error' => 'Метод не поддерживается'), JSON_UNESCAPED_UNICODE);
    exit;
}

require_once dirname(__DIR__) . '/pilot_lib.php';

legion_pilot_require_auth_json();

$payload = json_decode(file_get_contents('php://input'), true);
$id = is_array($payload) && isset($payload['id']) ? (string) $payload['id'] : '';

try {
    $data = legion_pilot_delete_history_entry($id);
    echo json_encode(array(
        'success' => true,
        'history' => isset($data['history']) ? $data['history'] : array(),
        'achievements' => isset($data['achievements']) ? $data['achievements'] : array(),
        'updatedAt' => $data['updatedAt'],
    ), JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
}
