<?php

/**
 * Клубные данные в MySQL: элита групп, достижения по scope, снимки, агрегированная история.
 */

require_once __DIR__ . '/pilot_db_lib.php';
require_once __DIR__ . '/ranks_lib.php';
require_once __DIR__ . '/storage_lib.php';

define('LEGION_CLUB_JSON_MIGRATED_META', 'club_json_migrated_v1');

function legion_club_storage_enabled() {
    return function_exists('legion_pilot_db_enabled') && legion_pilot_db_enabled();
}

function legion_club_storage_init_schema(PDO $pdo, $driver) {
    if ($driver === 'mysql') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS legion_coach_elite (
                coach_slug VARCHAR(64) NOT NULL PRIMARY KEY,
                elite_names MEDIUMTEXT NOT NULL,
                last_rotation_month VARCHAR(7) NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS legion_scope_achievements (
                scope VARCHAR(64) NOT NULL,
                person_name VARCHAR(255) NOT NULL,
                achievement_id VARCHAR(64) NOT NULL,
                granted_at DATE NOT NULL,
                PRIMARY KEY (scope, person_name, achievement_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS legion_scope_snapshots (
                scope VARCHAR(64) NOT NULL,
                snapshot_kind VARCHAR(32) NOT NULL,
                payload MEDIUMTEXT NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (scope, snapshot_kind)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS legion_coach_elite (
            coach_slug TEXT NOT NULL PRIMARY KEY,
            elite_names TEXT NOT NULL,
            last_rotation_month TEXT,
            updated_at TEXT NOT NULL
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS legion_scope_achievements (
            scope TEXT NOT NULL,
            person_name TEXT NOT NULL,
            achievement_id TEXT NOT NULL,
            granted_at TEXT NOT NULL,
            PRIMARY KEY (scope, person_name, achievement_id)
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS legion_scope_snapshots (
            scope TEXT NOT NULL,
            snapshot_kind TEXT NOT NULL,
            payload TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            PRIMARY KEY (scope, snapshot_kind)
        )
    ");
}

function legion_club_storage_pdo() {
    if (!legion_club_storage_enabled()) {
        return null;
    }
    return legion_pilot_db_pdo();
}

function legion_club_storage_now_sql() {
    return legion_pilot_db_now_sql();
}

function legion_club_storage_ensure_migrated() {
    static $running = false;

    $pdo = legion_club_storage_pdo();
    if (!$pdo) {
        return false;
    }
    if ($running) {
        return true;
    }
    if (legion_pilot_db_meta_get($pdo, LEGION_CLUB_JSON_MIGRATED_META, '') !== '') {
        return true;
    }

    $running = true;
    try {
        legion_club_migrate_elite_from_json($pdo);
        legion_club_migrate_achievements_from_json($pdo);
        legion_club_migrate_snapshots_from_json($pdo);
        legion_pilot_db_meta_set($pdo, LEGION_CLUB_JSON_MIGRATED_META, legion_club_storage_now_sql());
    } finally {
        $running = false;
    }
    return true;
}

function legion_club_migrate_elite_from_json(PDO $pdo) {
    $file = __DIR__ . '/elite.json';
    if (!is_file($file)) {
        return;
    }
    $all = storage_read_json($file, array());
    if (!is_array($all) || count($all) === 0) {
        return;
    }
    foreach ($all as $scope => $scopeData) {
        if (!is_array($scopeData) || storage_validate_scope($scope, false) === null) {
            continue;
        }
        $elite = isset($scopeData['elite']) && is_array($scopeData['elite']) ? $scopeData['elite'] : array();
        $month = isset($scopeData['lastRotationMonth']) ? $scopeData['lastRotationMonth'] : null;
        legion_club_save_elite($scope, $elite, $month, $pdo);
    }
}

function legion_club_migrate_achievements_from_json(PDO $pdo) {
    $file = __DIR__ . '/achievements.json';
    if (!is_file($file)) {
        return;
    }
    $all = storage_read_json($file, array());
    if (!is_array($all) || count($all) === 0) {
        return;
    }
    foreach ($all as $scope => $scopeData) {
        if (!is_array($scopeData) || storage_validate_scope($scope) === null) {
            continue;
        }
        legion_club_save_scope_achievements($scope, $scopeData, $pdo);
    }
}

function legion_club_migrate_snapshots_from_json(PDO $pdo) {
    $resultsFile = __DIR__ . '/last_results.json';
    if (is_file($resultsFile)) {
        $all = storage_read_json($resultsFile, array());
        if (is_array($all)) {
            foreach ($all as $scope => $snapshot) {
                if (storage_validate_scope($scope) === null || !is_array($snapshot)) {
                    continue;
                }
                legion_club_save_snapshot($scope, 'results', $snapshot, $pdo);
            }
        }
    }

    $ranksFile = __DIR__ . '/last_ranks.json';
    if (is_file($ranksFile)) {
        $all = storage_read_json($ranksFile, array());
        if (is_array($all)) {
            foreach ($all as $scope => $snapshot) {
                if (storage_validate_scope($scope) === null || !is_array($snapshot)) {
                    continue;
                }
                legion_club_save_snapshot($scope, 'ranks', $snapshot, $pdo);
            }
        }
    }

    $metaFile = __DIR__ . '/snapshot_meta.json';
    if (is_file($metaFile)) {
        $meta = storage_read_json($metaFile, array());
        if (is_array($meta) && count($meta) > 0) {
            legion_club_save_snapshot('global', 'meta', $meta, $pdo);
        }
    }
}

function legion_club_load_elite($coachSlug, PDO $pdo = null) {
    $coachSlug = storage_validate_scope($coachSlug, false);
    if ($coachSlug === null) {
        return array('elite' => array(), 'lastRotationMonth' => null);
    }
    if (!legion_club_storage_enabled()) {
        return legion_club_load_elite_from_json($coachSlug);
    }
    legion_club_storage_ensure_migrated();
    $pdo = $pdo ?: legion_club_storage_pdo();
    if (!$pdo) {
        return legion_club_load_elite_from_json($coachSlug);
    }

    $stmt = $pdo->prepare('
        SELECT elite_names, last_rotation_month
        FROM legion_coach_elite
        WHERE coach_slug = ?
        LIMIT 1
    ');
    $stmt->execute(array($coachSlug));
    $row = $stmt->fetch();
    if (!$row) {
        return array('elite' => array(), 'lastRotationMonth' => null);
    }

    $elite = json_decode((string) $row['elite_names'], true);
    return array(
        'elite' => is_array($elite) ? array_values($elite) : array(),
        'lastRotationMonth' => $row['last_rotation_month'] !== null && $row['last_rotation_month'] !== ''
            ? (string) $row['last_rotation_month']
            : null,
    );
}

function legion_club_load_elite_from_json($coachSlug) {
    $file = __DIR__ . '/elite.json';
    $all = storage_read_json($file, array());
    $scopeData = isset($all[$coachSlug]) && is_array($all[$coachSlug]) ? $all[$coachSlug] : array();
    return array(
        'elite' => isset($scopeData['elite']) && is_array($scopeData['elite']) ? array_values($scopeData['elite']) : array(),
        'lastRotationMonth' => isset($scopeData['lastRotationMonth']) ? $scopeData['lastRotationMonth'] : null,
    );
}

function legion_club_save_elite($coachSlug, array $elite, $lastRotationMonth = null, PDO $pdo = null) {
    $coachSlug = storage_validate_scope($coachSlug, false);
    if ($coachSlug === null) {
        throw new InvalidArgumentException('Неверный scope');
    }
    if (!legion_club_storage_enabled()) {
        return legion_club_save_elite_to_json($coachSlug, $elite, $lastRotationMonth);
    }
    legion_club_storage_ensure_migrated();
    $pdo = $pdo ?: legion_club_storage_pdo();
    if (!$pdo) {
        return legion_club_save_elite_to_json($coachSlug, $elite, $lastRotationMonth);
    }

    $names = array();
    foreach ($elite as $name) {
        $norm = legion_normalize_person_name($name);
        if ($norm !== '') {
            $names[] = $norm;
        }
    }

    $driver = legion_pilot_db_config()['driver'];
    if ($driver === 'mysql') {
        $stmt = $pdo->prepare('
            INSERT INTO legion_coach_elite (coach_slug, elite_names, last_rotation_month, updated_at)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                elite_names = VALUES(elite_names),
                last_rotation_month = VALUES(last_rotation_month),
                updated_at = VALUES(updated_at)
        ');
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO legion_coach_elite (coach_slug, elite_names, last_rotation_month, updated_at)
            VALUES (?, ?, ?, ?)
            ON CONFLICT(coach_slug) DO UPDATE SET
                elite_names = excluded.elite_names,
                last_rotation_month = excluded.last_rotation_month,
                updated_at = excluded.updated_at
        ');
    }
    $stmt->execute(array(
        $coachSlug,
        json_encode($names, JSON_UNESCAPED_UNICODE),
        $lastRotationMonth !== null && $lastRotationMonth !== '' ? (string) $lastRotationMonth : null,
        legion_club_storage_now_sql(),
    ));
    return true;
}

function legion_club_save_elite_to_json($coachSlug, array $elite, $lastRotationMonth = null) {
    $file = __DIR__ . '/elite.json';
    $all = storage_read_json($file, array());
    $all[$coachSlug] = array(
        'elite' => array_values($elite),
        'lastRotationMonth' => $lastRotationMonth,
    );
    return storage_write_json($file, $all);
}

function legion_club_load_scope_achievements($scope, PDO $pdo = null) {
    $scope = storage_validate_scope($scope);
    if ($scope === null) {
        return array();
    }
    if (!legion_club_storage_enabled()) {
        return legion_club_load_scope_achievements_from_json($scope);
    }
    legion_club_storage_ensure_migrated();
    $pdo = $pdo ?: legion_club_storage_pdo();
    if (!$pdo) {
        return legion_club_load_scope_achievements_from_json($scope);
    }

    $stmt = $pdo->prepare('
        SELECT person_name, achievement_id, granted_at
        FROM legion_scope_achievements
        WHERE scope = ?
        ORDER BY granted_at ASC, achievement_id ASC
    ');
    $stmt->execute(array($scope));
    $out = array();
    while ($row = $stmt->fetch()) {
        $name = legion_normalize_person_name($row['person_name']);
        if ($name === '') {
            continue;
        }
        if (!isset($out[$name])) {
            $out[$name] = array();
        }
        $out[$name][] = array(
            'id' => (string) $row['achievement_id'],
            'date' => (string) $row['granted_at'],
        );
    }
    return $out;
}

function legion_club_load_scope_achievements_from_json($scope) {
    $file = __DIR__ . '/achievements.json';
    $all = storage_read_json($file, array());
    return isset($all[$scope]) && is_array($all[$scope]) ? $all[$scope] : array();
}

function legion_club_save_scope_achievements($scope, array $data, PDO $pdo = null) {
    $scope = storage_validate_scope($scope);
    if ($scope === null) {
        throw new InvalidArgumentException('Неверный scope');
    }
    if (!legion_club_storage_enabled()) {
        return legion_club_save_scope_achievements_to_json($scope, $data);
    }
    legion_club_storage_ensure_migrated();
    $pdo = $pdo ?: legion_club_storage_pdo();
    if (!$pdo) {
        return legion_club_save_scope_achievements_to_json($scope, $data);
    }

    $pdo->prepare('DELETE FROM legion_scope_achievements WHERE scope = ?')->execute(array($scope));
    $insert = $pdo->prepare('
        INSERT INTO legion_scope_achievements (scope, person_name, achievement_id, granted_at)
        VALUES (?, ?, ?, ?)
    ');
    foreach ($data as $personName => $items) {
        if (!is_array($items)) {
            continue;
        }
        $name = legion_normalize_person_name($personName);
        if ($name === '') {
            continue;
        }
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['id'])) {
                continue;
            }
            $granted = isset($item['date']) ? (string) $item['date'] : date('Y-m-d');
            $insert->execute(array($scope, $name, (string) $item['id'], $granted));
        }
    }
    return true;
}

function legion_club_save_scope_achievements_to_json($scope, array $data) {
    $file = __DIR__ . '/achievements.json';
    $all = storage_read_json($file, array());
    $all[$scope] = $data;
    return storage_write_json($file, $all);
}

function legion_club_load_snapshot($scope, $kind, PDO $pdo = null) {
    $scope = storage_validate_scope($scope);
    if ($scope === null) {
        return array();
    }
    if (!legion_club_storage_enabled()) {
        return legion_club_load_snapshot_from_json($scope, $kind);
    }
    legion_club_storage_ensure_migrated();
    $pdo = $pdo ?: legion_club_storage_pdo();
    if (!$pdo) {
        return legion_club_load_snapshot_from_json($scope, $kind);
    }

    $stmt = $pdo->prepare('
        SELECT payload FROM legion_scope_snapshots
        WHERE scope = ? AND snapshot_kind = ?
        LIMIT 1
    ');
    $stmt->execute(array($scope, $kind));
    $row = $stmt->fetch();
    if (!$row) {
        return array();
    }
    $decoded = json_decode((string) $row['payload'], true);
    return is_array($decoded) ? $decoded : array();
}

function legion_club_load_snapshot_from_json($scope, $kind) {
    if ($kind === 'results') {
        $file = __DIR__ . '/last_results.json';
        $all = storage_read_json($file, array());
        $baseline = isset($all[$scope]) && is_array($all[$scope]) ? $all[$scope] : array();
        if (count($baseline) === 0 && $scope === 'global') {
            $baseline = storage_merge_last_results($all);
        }
        return $baseline;
    }
    if ($kind === 'ranks') {
        $file = __DIR__ . '/last_ranks.json';
        $all = storage_read_json($file, array());
        return isset($all[$scope]) && is_array($all[$scope]) ? $all[$scope] : array();
    }
    if ($kind === 'meta') {
        return storage_read_json(__DIR__ . '/snapshot_meta.json', array());
    }
    return array();
}

function legion_club_save_snapshot($scope, $kind, array $payload, PDO $pdo = null) {
    $scope = storage_validate_scope($scope);
    if ($scope === null) {
        throw new InvalidArgumentException('Неверный scope');
    }
    if (!legion_club_storage_enabled()) {
        return legion_club_save_snapshot_to_json($scope, $kind, $payload);
    }
    legion_club_storage_ensure_migrated();
    $pdo = $pdo ?: legion_club_storage_pdo();
    if (!$pdo) {
        return legion_club_save_snapshot_to_json($scope, $kind, $payload);
    }

    $driver = legion_pilot_db_config()['driver'];
    if ($driver === 'mysql') {
        $stmt = $pdo->prepare('
            INSERT INTO legion_scope_snapshots (scope, snapshot_kind, payload, updated_at)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE payload = VALUES(payload), updated_at = VALUES(updated_at)
        ');
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO legion_scope_snapshots (scope, snapshot_kind, payload, updated_at)
            VALUES (?, ?, ?, ?)
            ON CONFLICT(scope, snapshot_kind) DO UPDATE SET
                payload = excluded.payload,
                updated_at = excluded.updated_at
        ');
    }
    $stmt->execute(array(
        $scope,
        $kind,
        json_encode($payload, JSON_UNESCAPED_UNICODE),
        legion_club_storage_now_sql(),
    ));
    return true;
}

function legion_club_save_snapshot_to_json($scope, $kind, array $payload) {
    if ($kind === 'results') {
        $file = __DIR__ . '/last_results.json';
        $all = storage_read_json($file, array());
        $all[$scope] = $payload;
        return storage_write_json($file, $all);
    }
    if ($kind === 'ranks') {
        $file = __DIR__ . '/last_ranks.json';
        $all = storage_read_json($file, array());
        $all[$scope] = $payload;
        return storage_write_json($file, $all);
    }
    if ($kind === 'meta') {
        return storage_write_json(__DIR__ . '/snapshot_meta.json', $payload);
    }
    return false;
}

function legion_club_load_snapshot_meta() {
    if (!legion_club_storage_enabled()) {
        return storage_read_json(__DIR__ . '/snapshot_meta.json', array());
    }
    legion_club_storage_ensure_migrated();
    $meta = legion_club_load_snapshot('global', 'meta');
    return is_array($meta) ? $meta : array();
}

function legion_club_save_snapshot_meta(array $meta) {
    return legion_club_save_snapshot('global', 'meta', $meta);
}

function legion_club_load_all_history() {
    if (!legion_club_storage_enabled()) {
        $file = __DIR__ . '/history.json';
        return storage_read_json($file, array());
    }

    $pdo = legion_club_storage_pdo();
    if (!$pdo) {
        return storage_read_json(__DIR__ . '/history.json', array());
    }

    $stmt = $pdo->query('
        SELECT h.id, h.exercise, h.old_val, h.new_val, h.diff, h.created_at, a.name
        FROM pilot_history h
        INNER JOIN pilot_athletes a ON a.id = h.athlete_id
        ORDER BY h.created_at ASC, h.id ASC
    ');
    $history = array();
    while ($row = $stmt->fetch()) {
        $history[] = array(
            'id' => $row['id'],
            'date' => legion_pilot_db_format_ru_datetime($row['created_at']),
            'name' => legion_normalize_person_name($row['name']),
            'exercise' => $row['exercise'],
            'oldVal' => $row['old_val'] !== null ? (float) $row['old_val'] : null,
            'newVal' => $row['new_val'] !== null ? (float) $row['new_val'] : null,
            'diff' => (float) $row['diff'],
        );
    }
    return $history;
}

function legion_club_merge_achievement_maps(array $base, array $extra) {
    foreach ($extra as $name => $items) {
        if (!is_array($items)) {
            continue;
        }
        $norm = legion_normalize_person_name($name);
        if ($norm === '') {
            continue;
        }
        if (!isset($base[$norm])) {
            $base[$norm] = array();
        }
        $existingIds = array();
        foreach ($base[$norm] as $item) {
            if (is_array($item) && !empty($item['id'])) {
                $existingIds[$item['id']] = true;
            }
        }
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['id']) || isset($existingIds[$item['id']])) {
                continue;
            }
            $base[$norm][] = $item;
            $existingIds[$item['id']] = true;
        }
    }
    return $base;
}
