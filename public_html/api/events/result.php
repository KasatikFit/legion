<?php
/**
 * POST — лог попытки (арена / испытание).
 * Body: { token, kind, refId, reps, durationMs?, exercise?, completed? }
 */
require_once __DIR__ . '/_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    legion_mb_fail(405, 'Метод не поддерживается');
}

$payload = legion_mb_read_json();
$token = isset($payload['token']) ? (string) $payload['token'] : '';
$pdo = legion_mb_pdo();
$session = legion_mb_require_player_session($pdo, $token);
$playerId = (int) $session['player_id'];

$kind = isset($payload['kind']) && $payload['kind'] === 'challenge' ? 'challenge' : 'arena';
$refId = isset($payload['refId'])
    ? preg_replace('/[^a-z0-9_\-]/i', '', (string) $payload['refId'])
    : '';
if ($refId === '') {
    // Совместимость с level_id / challenge_id из плана
    if (!empty($payload['level_id'])) {
        $refId = preg_replace('/[^a-z0-9_\-]/i', '', (string) $payload['level_id']);
        $kind = 'arena';
    } elseif (!empty($payload['challenge_id'])) {
        $refId = preg_replace('/[^a-z0-9_\-]/i', '', (string) $payload['challenge_id']);
        $kind = 'challenge';
    }
}
if ($refId === '') {
    legion_mb_fail(400, 'Укажи refId (level или challenge)');
}

$reps = isset($payload['reps']) ? max(0, (int) $payload['reps']) : 0;
$duration = isset($payload['durationMs'])
    ? max(0, (int) $payload['durationMs'])
    : (isset($payload['duration_ms']) ? max(0, (int) $payload['duration_ms']) : null);
$exercise = isset($payload['exercise']) ? substr((string) $payload['exercise'], 0, 32) : 'pushup';
$completed = array_key_exists('completed', $payload) ? (!empty($payload['completed']) ? 1 : 0) : 1;

try {
    $stmt = $pdo->prepare(
        'INSERT INTO legion_mb_runs (player_id, kind, ref_id, reps, duration_ms, exercise, completed)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute(array($playerId, $kind, $refId, $reps, $duration, $exercise, $completed));
} catch (Exception $e) {
    legion_mb_fail(500, 'Не удалось записать результат');
}

legion_mb_ok(array(
    'success' => true,
    'id' => (int) $pdo->lastInsertId(),
));
