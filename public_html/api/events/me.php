<?php
require_once __DIR__ . '/_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    legion_mb_fail(405, 'Метод не поддерживается');
}

$token = isset($_GET['token']) ? (string) $_GET['token'] : '';
$pdo = legion_mb_pdo();
$session = legion_mb_require_player_session($pdo, $token);
$playerId = (int) $session['player_id'];

legion_mb_ok(array(
    'success' => true,
    'player' => legion_mb_fetch_player($pdo, $playerId),
    'progress' => legion_mb_load_progress($pdo, $playerId),
));
