<?php
require_once __DIR__ . '/_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    legion_mb_fail(405, 'Метод не поддерживается');
}

$payload = legion_mb_read_json();

try {
    $nickname = legion_mb_normalize_nickname(isset($payload['nickname']) ? $payload['nickname'] : '');
    $password = legion_mb_normalize_password(isset($payload['password']) ? $payload['password'] : '');
} catch (InvalidArgumentException $e) {
    legion_mb_fail(400, $e->getMessage());
}

$pdo = legion_mb_pdo();
$stmt = $pdo->prepare(
    'SELECT id, password_hash FROM legion_mb_players WHERE nickname = ? LIMIT 1'
);
$stmt->execute(array($nickname));
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row || !password_verify($password, $row['password_hash'])) {
    legion_mb_fail(401, 'Неверный никнейм или пароль');
}

$playerId = (int) $row['id'];

$profile = isset($payload['profile']) && is_array($payload['profile']) ? $payload['profile'] : array();
$progress = isset($payload['progress']) && is_array($payload['progress']) ? $payload['progress'] : array();
if ($profile || $progress) {
    try {
        legion_mb_sync_progress($pdo, $playerId, $profile, $progress);
    } catch (Exception $e) {
        legion_mb_fail(500, 'Не удалось синхронизировать прогресс');
    }
}

$token = legion_mb_create_session($pdo, 'player', $playerId);
$player = legion_mb_fetch_player($pdo, $playerId);
$serverProgress = legion_mb_load_progress($pdo, $playerId);

legion_mb_ok(array(
    'success' => true,
    'token' => $token,
    'player' => $player,
    'progress' => $serverProgress,
));
