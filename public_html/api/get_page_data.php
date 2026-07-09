<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Cache-Control: private, max-age=30');

require_once __DIR__ . '/page_data_lib.php';
require_once __DIR__ . '/storage_lib.php';

$coachSlug = isset($_GET['coach']) ? trim((string) $_GET['coach']) : null;
if ($coachSlug === '') {
    $coachSlug = null;
}

if ($coachSlug !== null && storage_validate_scope($coachSlug, false) === null) {
    http_response_code(400);
    echo json_encode(array('error' => 'Неверный coach'), JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(legion_load_page_data($coachSlug), JSON_UNESCAPED_UNICODE);
