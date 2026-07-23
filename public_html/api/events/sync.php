<?php
require_once __DIR__ . '/_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    legion_mb_fail(405, 'Метод не поддерживается');
}

$payload = legion_mb_read_json();
$token = isset($payload['token']) ? (string) $payload['token'] : '';
$pdo = legion_mb_pdo();
$session = legion_mb_require_player_session($pdo, $token);
$playerId = (int) $session['player_id'];

$profile = isset($payload['profile']) && is_array($payload['profile']) ? $payload['profile'] : array();
$progress = isset($payload['progress']) && is_array($payload['progress']) ? $payload['progress'] : array();

try {
    legion_mb_sync_progress($pdo, $playerId, $profile, $progress);
} catch (Exception $e) {
    legion_mb_fail(500, 'Синхронизация не удалась');
}

if (!empty($payload['run']) && is_array($payload['run'])) {
    $run = $payload['run'];
    $kind = isset($run['kind']) && $run['kind'] === 'challenge' ? 'challenge' : 'arena';
    $refId = isset($run['refId']) ? preg_replace('/[^a-z0-9_\-]/i', '', (string) $run['refId']) : '';
    if ($refId !== '') {
        $reps = isset($run['reps']) ? max(0, (int) $run['reps']) : 0;
        $duration = isset($run['durationMs']) ? max(0, (int) $run['durationMs']) : null;
        $exercise = isset($run['exercise']) ? substr((string) $run['exercise'], 0, 32) : 'pushup';
        $completed = empty($run['completed']) ? 0 : 1;
        $stmt = $pdo->prepare(
            'INSERT INTO legion_mb_runs (player_id, kind, ref_id, reps, duration_ms, exercise, completed)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(array($playerId, $kind, $refId, $reps, $duration, $exercise, $completed));
    }
}

$player = legion_mb_fetch_player($pdo, $playerId);
$serverProgress = legion_mb_load_progress($pdo, $playerId);

legion_mb_ok(array(
    'success' => true,
    'player' => $player,
    'progress' => $serverProgress,
));
