<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: private, max-age=10');

require_once dirname(__DIR__) . '/pilot_lib.php';

try {
    echo json_encode(legion_pilot_dataset_for_api(), JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'error' => $e->getMessage(),
    ), JSON_UNESCAPED_UNICODE);
}
