<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: private, max-age=10');

require_once __DIR__ . '/_lib.php';

$coach = legion_coach_json_slug_or_exit();
legion_coach_api_require_mysql($coach);

try {
    echo json_encode(legion_pilot_dataset_for_api($coach), JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
}
