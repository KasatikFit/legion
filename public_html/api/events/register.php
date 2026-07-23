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

$stmt = $pdo->prepare('SELECT id FROM legion_mb_players WHERE nickname = ? LIMIT 1');
$stmt->execute(array($nickname));
if ($stmt->fetch()) {
    legion_mb_fail(409, 'Такой никнейм уже занят');
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$display = isset($payload['displayName']) ? trim((string) $payload['displayName']) : '';
if ($display === '') {
    $display = $nickname;
}
if (mb_strlen($display) > 40) {
    $display = mb_substr($display, 0, 40);
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'INSERT INTO legion_mb_players (nickname, password_hash, display_name)
         VALUES (?, ?, ?)'
    );
    $stmt->execute(array($nickname, $hash, $display));
    $playerId = (int) $pdo->lastInsertId();

    $profile = isset($payload['profile']) && is_array($payload['profile']) ? $payload['profile'] : array();
    $progress = isset($payload['progress']) && is_array($payload['progress']) ? $payload['progress'] : array();
    if ($profile || $progress) {
        if (!isset($profile['name']) || $profile['name'] === '') {
            $profile['name'] = $display;
        }
        legion_mb_sync_progress($pdo, $playerId, $profile, $progress);
    }

    $token = legion_mb_create_session($pdo, 'player', $playerId);
    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    legion_mb_fail(500, 'Не удалось создать аккаунт');
}

$player = legion_mb_fetch_player($pdo, $playerId);
$serverProgress = legion_mb_load_progress($pdo, $playerId);

legion_mb_ok(array(
    'success' => true,
    'token' => $token,
    'player' => $player,
    'progress' => $serverProgress,
));
