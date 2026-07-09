<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_lib.php';

$coach = isset($_GET['coach']) ? (string) $_GET['coach'] : '';
try {
    $coach = legion_coach_normalize_slug($coach);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(array(
    'authenticated' => legion_coach_is_authenticated($coach),
    'authConfigured' => legion_coach_auth_is_configured($coach),
    'coach' => $coach,
), JSON_UNESCAPED_UNICODE);
