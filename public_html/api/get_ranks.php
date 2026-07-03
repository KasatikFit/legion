<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/ranks_lib.php';

$result = legion_load_all_ranks();

echo json_encode(array(
    'ranks' => $result['ranks'],
    'loaded' => $result['loaded'],
    'coaches' => $result['coaches'],
), JSON_UNESCAPED_UNICODE);
