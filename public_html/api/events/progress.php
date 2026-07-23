<?php
/**
 * GET  ?token=… — загрузить профиль + прогресс
 * POST { token, profile?, progress? } — upsert прогресса
 */
require_once __DIR__ . '/_lib.php';

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
$pdo = legion_mb_pdo();

if ($method === 'GET') {
    $token = isset($_GET['token']) ? (string) $_GET['token'] : '';
    $session = legion_mb_require_player_session($pdo, $token);
    $playerId = (int) $session['player_id'];
    legion_mb_ok(array(
        'success' => true,
        'player' => legion_mb_fetch_player($pdo, $playerId),
        'progress' => legion_mb_load_progress($pdo, $playerId),
    ));
}

if ($method !== 'POST') {
    legion_mb_fail(405, 'Метод не поддерживается');
}

$payload = legion_mb_read_json();
$token = isset($payload['token']) ? (string) $payload['token'] : '';
$session = legion_mb_require_player_session($pdo, $token);
$playerId = (int) $session['player_id'];

$profile = isset($payload['profile']) && is_array($payload['profile']) ? $payload['profile'] : array();
$progress = isset($payload['progress']) && is_array($payload['progress']) ? $payload['progress'] : array();

try {
    legion_mb_sync_progress($pdo, $playerId, $profile, $progress);
} catch (Exception $e) {
    legion_mb_fail(500, 'Не удалось сохранить прогресс');
}

legion_mb_ok(array(
    'success' => true,
    'player' => legion_mb_fetch_player($pdo, $playerId),
    'progress' => legion_mb_load_progress($pdo, $playerId),
));
