<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_lib.php';

$coach = isset($_GET['coach']) ? (string) $_GET['coach'] : '';
if ($coach === '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (is_array($payload) && isset($payload['coach'])) {
        $coach = (string) $payload['coach'];
    }
}

try {
    $coach = legion_coach_normalize_slug($coach);
    legion_coach_set_authenticated($coach, false);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(array('success' => true), JSON_UNESCAPED_UNICODE);
