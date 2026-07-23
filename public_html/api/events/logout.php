<?php
require_once __DIR__ . '/_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    legion_mb_fail(405, 'Метод не поддерживается');
}

$payload = legion_mb_read_json();
$token = isset($payload['token']) ? (string) $payload['token'] : '';
$pdo = legion_mb_pdo();

if ($token !== '') {
    $stmt = $pdo->prepare('DELETE FROM legion_mb_sessions WHERE token = ?');
    $stmt->execute(array($token));
}

legion_mb_ok(array('success' => true));
