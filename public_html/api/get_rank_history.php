<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$file = __DIR__ . '/rank_history.json';
if (file_exists($file)) {
    readfile($file);
} else {
    echo '[]';
}
