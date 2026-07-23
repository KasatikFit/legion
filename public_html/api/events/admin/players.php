<?php
require_once dirname(__DIR__) . '/_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    legion_mb_fail(405, 'Метод не поддерживается');
}

$token = isset($_GET['token']) ? (string) $_GET['token'] : '';
$pdo = legion_mb_pdo();
legion_mb_require_admin_session($pdo, $token);

$stmt = $pdo->query(
    'SELECT id, nickname, display_name, banners_cleared, challenges_cleared, lifetime_reps,
            streak_days, created_at, updated_at
     FROM legion_mb_players
     ORDER BY created_at DESC'
);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$players = array();
foreach ($rows as $row) {
    $players[] = array(
        'id' => (int) $row['id'],
        'nickname' => $row['nickname'],
        'displayName' => $row['display_name'],
        'bannersCleared' => (int) $row['banners_cleared'],
        'challengesCleared' => (int) $row['challenges_cleared'],
        'lifetimeReps' => (int) $row['lifetime_reps'],
        'streakDays' => (int) $row['streak_days'],
        'createdAt' => $row['created_at'],
        'updatedAt' => $row['updated_at'],
    );
}

legion_mb_ok(array('success' => true, 'players' => $players));
