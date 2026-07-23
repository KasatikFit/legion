<?php

require_once dirname(__DIR__) . '/pilot_db_lib.php';

function legion_mb_json_headers() {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
}

function legion_mb_read_json() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw !== false ? $raw : '', true);
    return is_array($data) ? $data : array();
}

function legion_mb_fail($code, $message) {
    http_response_code($code);
    legion_mb_json_headers();
    echo json_encode(array('error' => $message), JSON_UNESCAPED_UNICODE);
    exit;
}

function legion_mb_ok($payload) {
    legion_mb_json_headers();
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/** @return PDO */
function legion_mb_pdo() {
    $pdo = legion_pilot_db_pdo();
    if (!$pdo instanceof PDO) {
        legion_mb_fail(503, 'База данных недоступна');
    }
    return $pdo;
}

function legion_mb_normalize_nickname($nick) {
    $nick = trim((string) $nick);
    if ($nick === '') {
        throw new InvalidArgumentException('Укажи никнейм');
    }
    if (mb_strlen($nick) < 3 || mb_strlen($nick) > 40) {
        throw new InvalidArgumentException('Никнейм: от 3 до 40 символов');
    }
    if (!preg_match('/^[\p{L}\p{N}_.\-]+$/u', $nick)) {
        throw new InvalidArgumentException('Никнейм: буквы, цифры, _ . -');
    }
    return $nick;
}

function legion_mb_normalize_password($password) {
    $password = (string) $password;
    if (strlen($password) < 4) {
        throw new InvalidArgumentException('Пароль: минимум 4 символа');
    }
    if (strlen($password) > 72) {
        throw new InvalidArgumentException('Пароль слишком длинный');
    }
    return $password;
}

function legion_mb_new_token() {
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes(32));
    }
    if (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes(32));
    }
    $out = '';
    for ($i = 0; $i < 32; $i++) {
        $out .= chr(mt_rand(0, 255));
    }
    return bin2hex($out);
}

function legion_mb_session_expires_at() {
    return date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 90);
}

function legion_mb_purge_expired_sessions(PDO $pdo) {
    $pdo->exec("DELETE FROM legion_mb_sessions WHERE expires_at < NOW()");
}

function legion_mb_create_session(PDO $pdo, $kind, $playerId = null) {
    legion_mb_purge_expired_sessions($pdo);
    $token = legion_mb_new_token();
    $stmt = $pdo->prepare(
        'INSERT INTO legion_mb_sessions (token, kind, player_id, expires_at) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute(array($token, $kind, $playerId, legion_mb_session_expires_at()));
    return $token;
}

function legion_mb_require_player_session(PDO $pdo, $token) {
    $token = trim((string) $token);
    if ($token === '' || strlen($token) !== 64) {
        legion_mb_fail(401, 'Нужен вход');
    }
    $stmt = $pdo->prepare(
        "SELECT s.token, s.player_id, p.nickname, p.display_name, p.streak_days, p.lifetime_reps,
                p.last_active_day, p.banners_cleared, p.challenges_cleared
         FROM legion_mb_sessions s
         INNER JOIN legion_mb_players p ON p.id = s.player_id
         WHERE s.token = ? AND s.kind = 'player' AND s.expires_at > NOW()
         LIMIT 1"
    );
    $stmt->execute(array($token));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        legion_mb_fail(401, 'Сессия истекла — войди снова');
    }
    return $row;
}

function legion_mb_require_admin_session(PDO $pdo, $token) {
    $token = trim((string) $token);
    if ($token === '' || strlen($token) !== 64) {
        legion_mb_fail(401, 'Нужен вход админа');
    }
    $stmt = $pdo->prepare(
        "SELECT token FROM legion_mb_sessions
         WHERE token = ? AND kind = 'admin' AND expires_at > NOW()
         LIMIT 1"
    );
    $stmt->execute(array($token));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        legion_mb_fail(401, 'Сессия админа истекла');
    }
    return $row;
}

function legion_mb_admin_password() {
    $cfg = dirname(__DIR__) . '/events_admin_config.php';
    if (!is_file($cfg)) {
        return null;
    }
    require_once $cfg;
    if (!defined('EVENTS_ADMIN_PASSWORD')) {
        return null;
    }
    $pass = (string) EVENTS_ADMIN_PASSWORD;
    return $pass !== '' ? $pass : null;
}

function legion_mb_player_public_row(array $row) {
    return array(
        'id' => (int) $row['id'],
        'nickname' => $row['nickname'],
        'displayName' => $row['display_name'],
        'streakDays' => (int) $row['streak_days'],
        'lifetimeReps' => (int) $row['lifetime_reps'],
        'lastActiveDay' => $row['last_active_day'],
        'bannersCleared' => (int) $row['banners_cleared'],
        'challengesCleared' => (int) $row['challenges_cleared'],
    );
}

function legion_mb_load_progress(PDO $pdo, $playerId) {
    $levels = array();
    $stmt = $pdo->prepare(
        'SELECT level_id, total_reps, exercise, cleared_at FROM legion_mb_level_clears WHERE player_id = ?'
    );
    $stmt->execute(array($playerId));
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $levels[$row['level_id']] = array(
            'clearedAt' => date('c', strtotime($row['cleared_at'])),
            'totalReps' => (int) $row['total_reps'],
            'exercise' => $row['exercise'],
        );
    }

    $challenges = array();
    $stmt = $pdo->prepare(
        'SELECT challenge_id, time_ms, target_reps, best_reps, exercise, cleared_at
         FROM legion_mb_challenge_bests WHERE player_id = ?'
    );
    $stmt->execute(array($playerId));
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $item = array(
            'clearedAt' => date('c', strtotime($row['cleared_at'])),
            'timeMs' => (int) $row['time_ms'],
            'targetReps' => (int) $row['target_reps'],
            'exercise' => $row['exercise'],
        );
        if ($row['best_reps'] !== null) {
            $item['bestReps'] = (int) $row['best_reps'];
        }
        $challenges[$row['challenge_id']] = $item;
    }

    return array('levels' => $levels, 'challenges' => $challenges);
}

function legion_mb_refresh_aggregates(PDO $pdo, $playerId) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM legion_mb_level_clears WHERE player_id = ?');
    $stmt->execute(array($playerId));
    $banners = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM legion_mb_challenge_bests WHERE player_id = ?');
    $stmt->execute(array($playerId));
    $challenges = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'UPDATE legion_mb_players SET banners_cleared = ?, challenges_cleared = ? WHERE id = ?'
    );
    $stmt->execute(array($banners, $challenges, $playerId));
}

function legion_mb_sync_progress(PDO $pdo, $playerId, array $profile, array $progress) {
    $display = isset($profile['name']) ? trim((string) $profile['name']) : '';
    if (mb_strlen($display) > 40) {
        $display = mb_substr($display, 0, 40);
    }
    $streak = isset($profile['streakDays']) ? max(0, (int) $profile['streakDays']) : 0;
    $lifetime = isset($profile['lifetimeReps']) ? max(0, (int) $profile['lifetimeReps']) : 0;
    $lastDay = isset($profile['lastActiveDay']) ? (string) $profile['lastActiveDay'] : '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $lastDay)) {
        $lastDay = '';
    }

    $stmt = $pdo->prepare(
        'SELECT streak_days, lifetime_reps, last_active_day, display_name FROM legion_mb_players WHERE id = ?'
    );
    $stmt->execute(array($playerId));
    $cur = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cur) {
        throw new RuntimeException('Игрок не найден');
    }

    $mergedLifetime = max((int) $cur['lifetime_reps'], $lifetime);
    $mergedStreak = max((int) $cur['streak_days'], $streak);
    $mergedDay = $cur['last_active_day'];
    if ($lastDay !== '' && ($mergedDay === '' || $lastDay >= $mergedDay)) {
        $mergedDay = $lastDay;
        $mergedStreak = max($mergedStreak, $streak);
    }
    $mergedDisplay = $display !== '' ? $display : (string) $cur['display_name'];

    $stmt = $pdo->prepare(
        'UPDATE legion_mb_players
         SET display_name = ?, streak_days = ?, lifetime_reps = ?, last_active_day = ?
         WHERE id = ?'
    );
    $stmt->execute(array($mergedDisplay, $mergedStreak, $mergedLifetime, $mergedDay, $playerId));

    $levels = isset($progress['levels']) && is_array($progress['levels']) ? $progress['levels'] : array();
    foreach ($levels as $levelId => $clear) {
        if (!is_array($clear)) {
            continue;
        }
        $levelId = preg_replace('/[^a-z0-9_\-]/i', '', (string) $levelId);
        if ($levelId === '') {
            continue;
        }
        $reps = isset($clear['totalReps']) ? max(0, (int) $clear['totalReps']) : 0;
        $exercise = isset($clear['exercise']) ? substr((string) $clear['exercise'], 0, 32) : 'pushup';
        $clearedAt = isset($clear['clearedAt']) ? strtotime((string) $clear['clearedAt']) : false;
        $clearedSql = $clearedAt ? date('Y-m-d H:i:s', $clearedAt) : date('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'SELECT total_reps FROM legion_mb_level_clears WHERE player_id = ? AND level_id = ?'
        );
        $stmt->execute(array($playerId, $levelId));
        $prev = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($prev) {
            if ($reps > (int) $prev['total_reps']) {
                $stmt = $pdo->prepare(
                    'UPDATE legion_mb_level_clears
                     SET total_reps = ?, exercise = ?, cleared_at = ?
                     WHERE player_id = ? AND level_id = ?'
                );
                $stmt->execute(array($reps, $exercise, $clearedSql, $playerId, $levelId));
            }
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO legion_mb_level_clears (player_id, level_id, total_reps, exercise, cleared_at)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute(array($playerId, $levelId, $reps, $exercise, $clearedSql));
        }
    }

    $challenges = isset($progress['challenges']) && is_array($progress['challenges'])
        ? $progress['challenges']
        : array();
    foreach ($challenges as $challengeId => $best) {
        if (!is_array($best)) {
            continue;
        }
        $challengeId = preg_replace('/[^a-z0-9_\-]/i', '', (string) $challengeId);
        if ($challengeId === '') {
            continue;
        }
        $timeMs = isset($best['timeMs']) ? max(0, (int) $best['timeMs']) : 0;
        $targetReps = isset($best['targetReps']) ? max(0, (int) $best['targetReps']) : 0;
        $bestReps = array_key_exists('bestReps', $best) && $best['bestReps'] !== null
            ? max(0, (int) $best['bestReps'])
            : null;
        $exercise = isset($best['exercise']) ? substr((string) $best['exercise'], 0, 32) : 'pushup';
        $clearedAt = isset($best['clearedAt']) ? strtotime((string) $best['clearedAt']) : false;
        $clearedSql = $clearedAt ? date('Y-m-d H:i:s', $clearedAt) : date('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'SELECT time_ms, best_reps FROM legion_mb_challenge_bests WHERE player_id = ? AND challenge_id = ?'
        );
        $stmt->execute(array($playerId, $challengeId));
        $prev = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($prev) {
            $better = false;
            if ($bestReps !== null) {
                $prevBest = isset($prev['best_reps']) ? (int) $prev['best_reps'] : 0;
                $better = $bestReps > $prevBest;
            } else {
                $better = $timeMs > 0 && ($prev['time_ms'] == 0 || $timeMs < (int) $prev['time_ms']);
            }
            if ($better) {
                $stmt = $pdo->prepare(
                    'UPDATE legion_mb_challenge_bests
                     SET time_ms = ?, target_reps = ?, best_reps = ?, exercise = ?, cleared_at = ?
                     WHERE player_id = ? AND challenge_id = ?'
                );
                $stmt->execute(array(
                    $timeMs, $targetReps, $bestReps, $exercise, $clearedSql, $playerId, $challengeId
                ));
            }
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO legion_mb_challenge_bests
                 (player_id, challenge_id, time_ms, target_reps, best_reps, exercise, cleared_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute(array(
                $playerId, $challengeId, $timeMs, $targetReps, $bestReps, $exercise, $clearedSql
            ));
        }
    }

    legion_mb_refresh_aggregates($pdo, $playerId);
}

function legion_mb_fetch_player(PDO $pdo, $playerId) {
    $stmt = $pdo->prepare(
        'SELECT id, nickname, display_name, streak_days, lifetime_reps, last_active_day,
                banners_cleared, challenges_cleared
         FROM legion_mb_players WHERE id = ? LIMIT 1'
    );
    $stmt->execute(array($playerId));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? legion_mb_player_public_row($row) : null;
}
