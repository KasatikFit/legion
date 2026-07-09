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
$name = is_array($payload) && isset($payload['name']) ? (string) $payload['name'] : '';
$markIndex = is_array($payload) && isset($payload['markIndex']) ? $payload['markIndex'] : null;
$value = is_array($payload) && isset($payload['value']) ? $payload['value'] : 0;

try {
    $data = legion_pilot_update_rank_mark($name, $markIndex, $value);
    echo json_encode(array(
        'success' => true,
        'updatedAt' => $data['updatedAt'],
        'rankMarks' => isset($data['lastRankMarks']) ? $data['lastRankMarks'] : array(),
    ), JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
}
