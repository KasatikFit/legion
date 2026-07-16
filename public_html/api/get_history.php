<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/club_storage_lib.php';

echo json_encode(legion_club_load_all_history(), JSON_UNESCAPED_UNICODE);
