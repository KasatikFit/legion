<?php
require_once dirname(__DIR__) . '/_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    legion_mb_fail(405, 'Метод не поддерживается');
}

$payload = legion_mb_read_json();
$token = isset($payload['token']) ? (string) $payload['token'] : '';
$playerId = isset($payload['playerId']) ? (int) $payload['playerId'] : 0;
if ($playerId < 1) {
    legion_mb_fail(400, 'Неверный playerId');
}

try {
    $password = legion_mb_normalize_password(isset($payload['password']) ? $payload['password'] : '');
} catch (InvalidArgumentException $e) {
    legion_mb_fail(400, $e->getMessage());
}

$pdo = legion_mb_pdo();
legion_mb_require_admin_session($pdo, $token);

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('UPDATE legion_mb_players SET password_hash = ? WHERE id = ?');
$stmt->execute(array($hash, $playerId));

$check = $pdo->prepare('SELECT id FROM legion_mb_players WHERE id = ?');
$check->execute(array($playerId));
if (!$check->fetch()) {
    legion_mb_fail(404, 'Игрок не найден');
}

$stmt = $pdo->prepare("DELETE FROM legion_mb_sessions WHERE player_id = ? AND kind = 'player'");
$stmt->execute(array($playerId));

legion_mb_ok(array('success' => true));
