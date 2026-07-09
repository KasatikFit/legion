<?php

require_once __DIR__ . '/../pilot_lib.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $status = legion_pilot_db_status();
    $status['ok'] = !empty($status['enabled']);
    echo json_encode($status, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'ok' => false,
        'error' => $e->getMessage(),
    ), JSON_UNESCAPED_UNICODE);
}
