<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/history_lib.php';

echo json_encode(legion_load_snapshot_meta(), JSON_UNESCAPED_UNICODE);
