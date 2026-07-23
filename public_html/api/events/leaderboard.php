<?php
require_once __DIR__ . '/_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    legion_mb_fail(405, 'Метод не поддерживается');
}

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
if ($limit < 1) {
    $limit = 1;
}
if ($limit > 100) {
    $limit = 100;
}

$pdo = legion_mb_pdo();
try {
    $stmt = $pdo->query(
        'SELECT nickname, display_name, banners_cleared, challenges_cleared, lifetime_reps, streak_days
         FROM legion_mb_players
         ORDER BY banners_cleared DESC, challenges_cleared DESC, lifetime_reps DESC, nickname ASC
         LIMIT ' . $limit
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    legion_mb_fail(500, 'Нет таблиц Move Boss. Выполните api/migrations/events_mysql.sql в phpMyAdmin');
}
$list = array();
$rank = 1;
foreach ($rows as $row) {
    $list[] = array(
        'rank' => $rank++,
        'nickname' => $row['nickname'],
        'displayName' => $row['display_name'] !== '' ? $row['display_name'] : $row['nickname'],
        'bannersCleared' => (int) $row['banners_cleared'],
        'challengesCleared' => (int) $row['challenges_cleared'],
        'lifetimeReps' => (int) $row['lifetime_reps'],
        'streakDays' => (int) $row['streak_days'],
    );
}

legion_mb_ok(array('success' => true, 'leaders' => $list));
