<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$historyFile = __DIR__ . '/history.json';

if (file_exists($historyFile)) {
    readfile($historyFile);
} else {
    echo '[]';
}