<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/pilot_db_lib.php';

if (legion_pilot_db_enabled()) {
    echo json_encode(legion_pilot_db_load_all_rank_history(), JSON_UNESCAPED_UNICODE);
    exit;
}

$file = __DIR__ . '/rank_history.json';
if (file_exists($file)) {
    readfile($file);
} else {
    echo '[]';
}
