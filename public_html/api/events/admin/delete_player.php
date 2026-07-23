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

$pdo = legion_mb_pdo();
legion_mb_require_admin_session($pdo, $token);

$stmt = $pdo->prepare('DELETE FROM legion_mb_players WHERE id = ?');
$stmt->execute(array($playerId));
if ($stmt->rowCount() < 1) {
    legion_mb_fail(404, 'Игрок не найден');
}

legion_mb_ok(array('success' => true));
