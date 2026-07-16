<?php

/**
 * Хранилище пилотной группы в БД (SQLite по умолчанию или MySQL через pilot_db_config.php).
 */

define('LEGION_PILOT_DB_SCHEMA_VERSION', 5);
define('LEGION_PILOT_RANK_HISTORY_PER_ATHLETE', 50);

function legion_pilot_db_config() {
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $configPath = __DIR__ . '/pilot_db_config.php';
    if (is_file($configPath)) {
        require $configPath;
    }

    $driver = defined('PILOT_DB_DRIVER') ? strtolower((string) PILOT_DB_DRIVER) : 'sqlite';
    if ($driver === 'mysql') {
        $config = array(
            'driver' => 'mysql',
            'host' => defined('PILOT_DB_HOST') ? PILOT_DB_HOST : 'localhost',
            'dbname' => defined('PILOT_DB_NAME') ? PILOT_DB_NAME : '',
            'user' => defined('PILOT_DB_USER') ? PILOT_DB_USER : '',
            'pass' => defined('PILOT_DB_PASS') ? PILOT_DB_PASS : '',
            'charset' => defined('PILOT_DB_CHARSET') ? PILOT_DB_CHARSET : 'utf8mb4',
        );
        return $config;
    }

    $sqlitePath = defined('PILOT_DB_SQLITE_PATH')
        ? PILOT_DB_SQLITE_PATH
        : __DIR__ . '/data/pilot-demo.sqlite';

    $config = array(
        'driver' => 'sqlite',
        'path' => $sqlitePath,
    );
    return $config;
}

function legion_pilot_db_pdo() {
    static $pdo = null;
    static $initialized = false;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = legion_pilot_db_config();
    try {
        if ($cfg['driver'] === 'mysql') {
            if ($cfg['dbname'] === '' || $cfg['user'] === '') {
                return null;
            }
            $dsn = 'mysql:host=' . $cfg['host']
                . ';dbname=' . $cfg['dbname']
                . ';charset=' . $cfg['charset'];
            $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ));
        } else {
            $dir = dirname($cfg['path']);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $pdo = new PDO('sqlite:' . $cfg['path'], null, null, array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ));
            $pdo->exec('PRAGMA foreign_keys = ON');
        }

        if (!$initialized) {
            legion_pilot_db_init_schema($pdo, $cfg['driver']);
            legion_pilot_db_apply_migrations($pdo, $cfg['driver']);
            $initialized = true;
        }
    } catch (Exception $e) {
        $pdo = null;
        return null;
    }

    return $pdo;
}

function legion_pilot_db_enabled() {
    return legion_pilot_db_pdo() instanceof PDO;
}

function legion_pilot_db_storage_label() {
    if (!legion_pilot_db_enabled()) {
        return 'json';
    }
    $cfg = legion_pilot_db_config();
    return $cfg['driver'] === 'mysql' ? 'mysql' : 'sqlite';
}

function legion_pilot_db_init_schema(PDO $pdo, $driver) {
    if ($driver === 'mysql') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pilot_meta (
                meta_key VARCHAR(64) NOT NULL PRIMARY KEY,
                meta_value TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pilot_athletes (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                coach_slug VARCHAR(64) NOT NULL DEFAULT 'pilot-demo',
                name VARCHAR(255) NOT NULL,
                photo VARCHAR(512) NOT NULL DEFAULT '',
                birthdate DATE NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_pilot_athletes_coach_name (coach_slug, name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pilot_results (
                athlete_id INT UNSIGNED NOT NULL,
                exercise VARCHAR(32) NOT NULL,
                value DOUBLE NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (athlete_id, exercise),
                CONSTRAINT fk_pilot_results_athlete FOREIGN KEY (athlete_id)
                    REFERENCES pilot_athletes (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pilot_rank_marks (
                athlete_id INT UNSIGNED NOT NULL,
                mark_index SMALLINT UNSIGNED NOT NULL,
                value TINYINT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (athlete_id, mark_index),
                CONSTRAINT fk_pilot_rank_marks_athlete FOREIGN KEY (athlete_id)
                    REFERENCES pilot_athletes (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pilot_history (
                id VARCHAR(32) NOT NULL PRIMARY KEY,
                athlete_id INT UNSIGNED NOT NULL,
                exercise VARCHAR(32) NOT NULL,
                old_val DOUBLE NULL,
                new_val DOUBLE NULL,
                diff DOUBLE NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                KEY idx_pilot_history_athlete (athlete_id, created_at),
                CONSTRAINT fk_pilot_history_athlete FOREIGN KEY (athlete_id)
                    REFERENCES pilot_athletes (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pilot_achievements (
                athlete_id INT UNSIGNED NOT NULL,
                achievement_id VARCHAR(64) NOT NULL,
                granted_at DATE NOT NULL,
                PRIMARY KEY (athlete_id, achievement_id),
                CONSTRAINT fk_pilot_achievements_athlete FOREIGN KEY (athlete_id)
                    REFERENCES pilot_athletes (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pilot_rank_history (
                id VARCHAR(32) NOT NULL PRIMARY KEY,
                athlete_id INT UNSIGNED NOT NULL,
                event VARCHAR(32) NOT NULL,
                mark_index SMALLINT UNSIGNED NULL,
                old_val TINYINT UNSIGNED NULL,
                new_val TINYINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                KEY idx_pilot_rank_history_athlete (athlete_id, created_at),
                CONSTRAINT fk_pilot_rank_history_athlete FOREIGN KEY (athlete_id)
                    REFERENCES pilot_athletes (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        require_once __DIR__ . '/club_storage_lib.php';
        legion_club_storage_init_schema($pdo, 'mysql');
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pilot_meta (
            meta_key TEXT NOT NULL PRIMARY KEY,
            meta_value TEXT NOT NULL
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pilot_athletes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            coach_slug TEXT NOT NULL DEFAULT 'pilot-demo',
            name TEXT NOT NULL,
            photo TEXT NOT NULL DEFAULT '',
            birthdate TEXT NULL,
            created_at TEXT NOT NULL,
            UNIQUE (coach_slug, name)
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pilot_results (
            athlete_id INTEGER NOT NULL,
            exercise TEXT NOT NULL,
            value REAL NOT NULL DEFAULT 0,
            updated_at TEXT NOT NULL,
            PRIMARY KEY (athlete_id, exercise),
            FOREIGN KEY (athlete_id) REFERENCES pilot_athletes(id) ON DELETE CASCADE
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pilot_rank_marks (
            athlete_id INTEGER NOT NULL,
            mark_index INTEGER NOT NULL,
            value INTEGER NOT NULL DEFAULT 0,
            PRIMARY KEY (athlete_id, mark_index),
            FOREIGN KEY (athlete_id) REFERENCES pilot_athletes(id) ON DELETE CASCADE
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pilot_history (
            id TEXT NOT NULL PRIMARY KEY,
            athlete_id INTEGER NOT NULL,
            exercise TEXT NOT NULL,
            old_val REAL,
            new_val REAL,
            diff REAL NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            FOREIGN KEY (athlete_id) REFERENCES pilot_athletes(id) ON DELETE CASCADE
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pilot_history_athlete ON pilot_history(athlete_id, created_at)");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pilot_achievements (
            athlete_id INTEGER NOT NULL,
            achievement_id TEXT NOT NULL,
            granted_at TEXT NOT NULL,
            PRIMARY KEY (athlete_id, achievement_id),
            FOREIGN KEY (athlete_id) REFERENCES pilot_athletes(id) ON DELETE CASCADE
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pilot_rank_history (
            id TEXT NOT NULL PRIMARY KEY,
            athlete_id INTEGER NOT NULL,
            event TEXT NOT NULL,
            mark_index INTEGER,
            old_val INTEGER,
            new_val INTEGER,
            created_at TEXT NOT NULL,
            FOREIGN KEY (athlete_id) REFERENCES pilot_athletes(id) ON DELETE CASCADE
        )
    ");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pilot_rank_history_athlete ON pilot_rank_history(athlete_id, created_at)');
    require_once __DIR__ . '/club_storage_lib.php';
    legion_club_storage_init_schema($pdo, 'sqlite');
}

function legion_pilot_db_column_exists(PDO $pdo, $table, $column) {
    try {
        if (legion_pilot_db_config()['driver'] === 'mysql') {
            $stmt = $pdo->prepare('
                SELECT COUNT(*) AS c FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
            ');
            $stmt->execute(array($table, $column));
            $row = $stmt->fetch();
            return $row && (int) $row['c'] > 0;
        }
        $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
        while ($row = $stmt->fetch()) {
            if (isset($row['name']) && $row['name'] === $column) {
                return true;
            }
        }
    } catch (Exception $e) {
        return false;
    }
    return false;
}

function legion_pilot_db_apply_migrations(PDO $pdo, $driver) {
    if (!legion_pilot_db_column_exists($pdo, 'pilot_athletes', 'coach_slug')) {
        if ($driver === 'mysql') {
            $pdo->exec("ALTER TABLE pilot_athletes ADD COLUMN coach_slug VARCHAR(64) NOT NULL DEFAULT 'pilot-demo' AFTER id");
            $pdo->exec('UPDATE pilot_athletes SET coach_slug = \'pilot-demo\' WHERE coach_slug = \'\' OR coach_slug IS NULL');
            try {
                $pdo->exec('ALTER TABLE pilot_athletes DROP INDEX uq_pilot_athletes_name');
            } catch (Exception $e) {
                // index may have another name on older installs
            }
            $pdo->exec('ALTER TABLE pilot_athletes ADD UNIQUE KEY uq_pilot_athletes_coach_name (coach_slug, name)');
        } else {
            $pdo->exec("ALTER TABLE pilot_athletes ADD COLUMN coach_slug TEXT NOT NULL DEFAULT 'pilot-demo'");
            $pdo->exec("UPDATE pilot_athletes SET coach_slug = 'pilot-demo' WHERE coach_slug = '' OR coach_slug IS NULL");
        }
        $legacyUpdated = legion_pilot_db_meta_get($pdo, 'updated_at', '');
        if ($legacyUpdated !== '' && legion_pilot_db_meta_get($pdo, 'pilot-demo:updated_at', '') === '') {
            legion_pilot_db_meta_set($pdo, 'pilot-demo:updated_at', $legacyUpdated);
        }
    }
    if (!legion_pilot_db_column_exists($pdo, 'pilot_athletes', 'birthdate')) {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE pilot_athletes ADD COLUMN birthdate DATE NULL AFTER photo');
        } else {
            $pdo->exec('ALTER TABLE pilot_athletes ADD COLUMN birthdate TEXT NULL');
        }
    }
    legion_pilot_db_meta_set($pdo, 'schema_version', (string) LEGION_PILOT_DB_SCHEMA_VERSION);
}

function legion_pilot_db_scoped_meta_key($coachSlug, $key) {
    $coachSlug = trim((string) $coachSlug);
    if ($coachSlug === '') {
        return $key;
    }
    return $coachSlug . ':' . $key;
}

function legion_pilot_db_meta_get(PDO $pdo, $key, $default = '', $coachSlug = '') {
    if ($coachSlug !== '') {
        $key = legion_pilot_db_scoped_meta_key($coachSlug, $key);
    }
    $stmt = $pdo->prepare('SELECT meta_value FROM pilot_meta WHERE meta_key = ? LIMIT 1');
    $stmt->execute(array($key));
    $row = $stmt->fetch();
    return $row ? (string) $row['meta_value'] : $default;
}

function legion_pilot_db_meta_set(PDO $pdo, $key, $value, $coachSlug = '') {
    if ($coachSlug !== '') {
        $key = legion_pilot_db_scoped_meta_key($coachSlug, $key);
    }
    $driver = legion_pilot_db_config()['driver'];
    if ($driver === 'mysql') {
        $stmt = $pdo->prepare('
            INSERT INTO pilot_meta (meta_key, meta_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)
        ');
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO pilot_meta (meta_key, meta_value) VALUES (?, ?)
            ON CONFLICT(meta_key) DO UPDATE SET meta_value = excluded.meta_value
        ');
    }
    $stmt->execute(array($key, $value));
}

function legion_pilot_db_now_sql() {
    return date('Y-m-d H:i:s');
}

function legion_pilot_db_format_ru_datetime($sqlDatetime) {
    $sqlDatetime = trim((string) $sqlDatetime);
    if ($sqlDatetime === '') {
        return date('d.m.Y, H:i:s');
    }
    $ts = strtotime($sqlDatetime);
    if ($ts === false) {
        return $sqlDatetime;
    }
    return date('d.m.Y, H:i:s', $ts);
}

function legion_pilot_db_athlete_count(PDO $pdo, $coachSlug = '') {
    if ($coachSlug !== '') {
        $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM pilot_athletes WHERE coach_slug = ?');
        $stmt->execute(array($coachSlug));
        $row = $stmt->fetch();
        return $row ? (int) $row['c'] : 0;
    }
    $row = $pdo->query('SELECT COUNT(*) AS c FROM pilot_athletes')->fetch();
    return $row ? (int) $row['c'] : 0;
}

function legion_pilot_db_ensure_ready($coachSlug = 'pilot-demo') {
    $pdo = legion_pilot_db_pdo();
    if (!$pdo) {
        return false;
    }

    static $readyByCoach = array();
    if (!empty($readyByCoach[$coachSlug])) {
        return true;
    }

    if (legion_pilot_db_athlete_count($pdo, $coachSlug) > 0) {
        $readyByCoach[$coachSlug] = true;
        return true;
    }

    if (legion_pilot_db_migrate_from_json($pdo, $coachSlug)) {
        $readyByCoach[$coachSlug] = true;
        return true;
    }

    if ($coachSlug === 'pilot-demo') {
        legion_pilot_db_import_dataset($pdo, legion_pilot_default_dataset(), 'pilot-demo');
    }
    $readyByCoach[$coachSlug] = true;
    return true;
}

function legion_pilot_db_migrate_from_json(PDO $pdo, $coachSlug = 'pilot-demo') {
    if (!function_exists('legion_coach_data_json_path')) {
        require_once __DIR__ . '/coach_data_lib.php';
    }
    $path = $coachSlug === 'pilot-demo'
        ? (function_exists('legion_pilot_data_path') ? legion_pilot_data_path('pilot-demo') : __DIR__ . '/data/pilot-demo.json')
        : legion_coach_data_json_path($coachSlug);
    if (!is_file($path)) {
        return false;
    }

    $raw = @file_get_contents($path);
    if ($raw === false) {
        return false;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['athletes']) || !is_array($data['athletes'])) {
        return false;
    }

    legion_pilot_db_import_dataset($pdo, $data, $coachSlug);
    legion_pilot_db_meta_set($pdo, 'migrated_from_json', legion_pilot_db_now_sql(), $coachSlug);
    return true;
}

function legion_pilot_db_import_dataset(PDO $pdo, array $data, $coachSlug = 'pilot-demo') {
    $pdo->beginTransaction();
    try {
        legion_pilot_db_write_dataset($pdo, $data, false, $coachSlug);
        $updatedAt = isset($data['updatedAt']) ? (string) $data['updatedAt'] : legion_pilot_db_format_ru_datetime(legion_pilot_db_now_sql());
        legion_pilot_db_meta_set($pdo, 'updated_at', $updatedAt, $coachSlug);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function legion_pilot_db_load_dataset($coachSlug = 'pilot-demo', array $options = array()) {
    if (!legion_pilot_db_ensure_ready($coachSlug)) {
        throw new RuntimeException('База данных пилотной группы недоступна');
    }

    $includeHistory = !array_key_exists('includeHistory', $options) || $options['includeHistory'];
    $includeAchievements = !array_key_exists('includeAchievements', $options) || $options['includeAchievements'];

    $pdo = legion_pilot_db_pdo();
    if (!$pdo) {
        throw new RuntimeException('База данных пилотной группы недоступна');
    }

    $exercises = legion_pilot_exercise_keys();
    $athletes = array();
    $nameById = array();

    $rowsStmt = $pdo->prepare('SELECT id, name, photo, birthdate FROM pilot_athletes WHERE coach_slug = ? ORDER BY id ASC');
    $rowsStmt->execute(array($coachSlug));
    $rows = $rowsStmt->fetchAll();
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $nameById[$id] = $row['name'];
        $athletes[$id] = array(
            'name' => $row['name'],
            'photo' => (string) $row['photo'],
            'birthdate' => !empty($row['birthdate']) ? (string) $row['birthdate'] : null,
            'rankMarks' => legion_pilot_default_marks(0, 0, 0),
        );
        foreach ($exercises as $key) {
            $athletes[$id][$key] = 0;
        }
    }

    if (!empty($athletes)) {
        $ids = array_keys($athletes);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare('SELECT athlete_id, exercise, value FROM pilot_results WHERE athlete_id IN (' . $placeholders . ')');
        $stmt->execute($ids);
        while ($r = $stmt->fetch()) {
            $aid = (int) $r['athlete_id'];
            if (!isset($athletes[$aid])) {
                continue;
            }
            $ex = $r['exercise'];
            if (in_array($ex, $exercises, true)) {
                $athletes[$aid][$ex] = (float) $r['value'];
            }
        }

        $stmt = $pdo->prepare('SELECT athlete_id, mark_index, value FROM pilot_rank_marks WHERE athlete_id IN (' . $placeholders . ') ORDER BY athlete_id, mark_index');
        $stmt->execute($ids);
        $marksByAthlete = array();
        while ($r = $stmt->fetch()) {
            $aid = (int) $r['athlete_id'];
            if (!isset($athletes[$aid])) {
                continue;
            }
            if (!isset($marksByAthlete[$aid])) {
                $marksByAthlete[$aid] = legion_pilot_default_marks(0, 0, 0);
            }
            $idx = (int) $r['mark_index'];
            if ($idx >= 0 && $idx < 60) {
                $marksByAthlete[$aid][$idx] = ((int) $r['value'] > 0) ? 1 : 0;
            }
        }
        foreach ($marksByAthlete as $aid => $marks) {
            $athletes[$aid]['rankMarks'] = legion_pilot_normalize_marks($marks);
        }
    }

    $history = array();
    if ($includeHistory) {
        $histStmt = $pdo->prepare('
            SELECT h.id, h.exercise, h.old_val, h.new_val, h.diff, h.created_at, a.name
            FROM pilot_history h
            INNER JOIN pilot_athletes a ON a.id = h.athlete_id
            WHERE a.coach_slug = ?
            ORDER BY h.created_at ASC, h.id ASC
        ');
        $histStmt->execute(array($coachSlug));
        while ($r = $histStmt->fetch()) {
            $history[] = array(
                'id' => $r['id'],
                'date' => legion_pilot_db_format_ru_datetime($r['created_at']),
                'name' => legion_normalize_person_name($r['name']),
                'exercise' => $r['exercise'],
                'oldVal' => $r['old_val'] !== null ? (float) $r['old_val'] : null,
                'newVal' => $r['new_val'] !== null ? (float) $r['new_val'] : null,
                'diff' => (float) $r['diff'],
            );
        }
    }

    $achievements = array();
    if ($includeAchievements) {
        $achStmt = $pdo->prepare('
            SELECT a.name, pa.achievement_id, pa.granted_at
            FROM pilot_achievements pa
            INNER JOIN pilot_athletes a ON a.id = pa.athlete_id
            WHERE a.coach_slug = ?
            ORDER BY pa.granted_at ASC, pa.achievement_id ASC
        ');
        $achStmt->execute(array($coachSlug));
        while ($r = $achStmt->fetch()) {
            $name = legion_normalize_person_name($r['name']);
            if ($name === '') {
                continue;
            }
            if (!isset($achievements[$name])) {
                $achievements[$name] = array();
            }
            $achievements[$name][] = array(
                'id' => $r['achievement_id'],
                'date' => $r['granted_at'],
            );
        }
    }

    if (!function_exists('legion_coach_meta')) {
        require_once __DIR__ . '/coach_data_lib.php';
    }
    $meta = legion_coach_meta($coachSlug);
    $updatedAt = legion_pilot_db_meta_get($pdo, 'updated_at', '', $coachSlug);
    if ($updatedAt === '') {
        $updatedAt = legion_pilot_db_format_ru_datetime(legion_pilot_db_now_sql());
    }

    $list = array();
    foreach ($athletes as $row) {
        $list[] = $row;
    }

    return array(
        'slug' => $coachSlug,
        'coachName' => $meta['name'],
        'updatedAt' => $updatedAt,
        'athletes' => $list,
        'history' => $history,
        'achievements' => $achievements,
    );
}

function legion_pilot_db_write_dataset(PDO $pdo, array $data, $touchUpdatedAt = true, $coachSlug = 'pilot-demo') {
    $exercises = legion_pilot_exercise_keys();
    $now = legion_pilot_db_now_sql();
    $athletes = isset($data['athletes']) && is_array($data['athletes']) ? $data['athletes'] : array();

    $keepIds = array();
    $selectAthlete = $pdo->prepare('SELECT id FROM pilot_athletes WHERE coach_slug = ? AND name = ? LIMIT 1');
    $insertAthlete = $pdo->prepare('INSERT INTO pilot_athletes (coach_slug, name, photo, birthdate, created_at) VALUES (?, ?, ?, ?, ?)');
    $updateAthlete = $pdo->prepare('UPDATE pilot_athletes SET photo = ?, birthdate = ? WHERE id = ?');

    $driver = legion_pilot_db_config()['driver'];
    if ($driver === 'mysql') {
        $upsertResult = $pdo->prepare('
            INSERT INTO pilot_results (athlete_id, exercise, value, updated_at) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)
        ');
        $upsertMark = $pdo->prepare('
            INSERT INTO pilot_rank_marks (athlete_id, mark_index, value) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE value = VALUES(value)
        ');
    } else {
        $upsertResult = $pdo->prepare('
            INSERT INTO pilot_results (athlete_id, exercise, value, updated_at) VALUES (?, ?, ?, ?)
            ON CONFLICT(athlete_id, exercise) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at
        ');
        $upsertMark = $pdo->prepare('
            INSERT INTO pilot_rank_marks (athlete_id, mark_index, value) VALUES (?, ?, ?)
            ON CONFLICT(athlete_id, mark_index) DO UPDATE SET value = excluded.value
        ');
    }

    foreach ($athletes as $row) {
        if (!is_array($row) || empty($row['name'])) {
            continue;
        }
        $name = legion_normalize_person_name($row['name']);
        $photo = isset($row['photo']) ? (string) $row['photo'] : '';
        $birthdate = null;
        if (isset($row['birthdate']) && $row['birthdate'] !== '' && $row['birthdate'] !== null) {
            $birthdate = (string) $row['birthdate'];
        }

        $selectAthlete->execute(array($coachSlug, $name));
        $found = $selectAthlete->fetch();
        if ($found) {
            $athleteId = (int) $found['id'];
            $updateAthlete->execute(array($photo, $birthdate, $athleteId));
        } else {
            $insertAthlete->execute(array($coachSlug, $name, $photo, $birthdate, $now));
            $athleteId = (int) $pdo->lastInsertId();
        }
        $keepIds[] = $athleteId;

        foreach ($exercises as $key) {
            $val = isset($row[$key]) && is_numeric($row[$key]) ? (float) $row[$key] : 0;
            $upsertResult->execute(array($athleteId, $key, $val, $now));
        }

        $marks = legion_pilot_athlete_marks($row);
        for ($i = 0; $i < 60; $i++) {
            $upsertMark->execute(array($athleteId, $i, (int) $marks[$i]));
        }
    }

    if (!empty($keepIds)) {
        $placeholders = implode(',', array_fill(0, count($keepIds), '?'));
        $params = array_merge(array($coachSlug), $keepIds);
        $del = $pdo->prepare('DELETE FROM pilot_athletes WHERE coach_slug = ? AND id NOT IN (' . $placeholders . ')');
        $del->execute($params);
    } else {
        $del = $pdo->prepare('DELETE FROM pilot_athletes WHERE coach_slug = ?');
        $del->execute(array($coachSlug));
    }

    $delHist = $pdo->prepare('
        DELETE FROM pilot_history WHERE athlete_id IN (
            SELECT id FROM pilot_athletes WHERE coach_slug = ?
        )
    ');
    if ($driver === 'mysql') {
        $pdo->prepare('DELETE h FROM pilot_history h INNER JOIN pilot_athletes a ON a.id = h.athlete_id WHERE a.coach_slug = ?')
            ->execute(array($coachSlug));
    } else {
        $delHist->execute(array($coachSlug));
    }

    $history = isset($data['history']) && is_array($data['history']) ? $data['history'] : array();
    $insertHistory = $pdo->prepare('
        INSERT INTO pilot_history (id, athlete_id, exercise, old_val, new_val, diff, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $aidStmt = $pdo->prepare('SELECT id FROM pilot_athletes WHERE coach_slug = ? AND name = ? LIMIT 1');
    foreach ($history as $entry) {
        if (!is_array($entry) || empty($entry['name'])) {
            continue;
        }
        $normName = legion_normalize_person_name($entry['name']);
        $aidStmt->execute(array($coachSlug, $normName));
        $aidRow = $aidStmt->fetch();
        if (!$aidRow) {
            continue;
        }
        $createdAt = isset($entry['date']) ? legion_pilot_db_parse_ru_datetime($entry['date']) : $now;
        $insertHistory->execute(array(
            isset($entry['id']) ? (string) $entry['id'] : legion_pilot_new_history_id(),
            (int) $aidRow['id'],
            isset($entry['exercise']) ? (string) $entry['exercise'] : '',
            isset($entry['oldVal']) && is_numeric($entry['oldVal']) ? (float) $entry['oldVal'] : null,
            isset($entry['newVal']) && is_numeric($entry['newVal']) ? (float) $entry['newVal'] : null,
            isset($entry['diff']) && is_numeric($entry['diff']) ? (float) $entry['diff'] : 0,
            $createdAt,
        ));
    }

    if ($driver === 'mysql') {
        $pdo->prepare('DELETE pa FROM pilot_achievements pa INNER JOIN pilot_athletes a ON a.id = pa.athlete_id WHERE a.coach_slug = ?')
            ->execute(array($coachSlug));
    } else {
        $pdo->prepare('DELETE FROM pilot_achievements WHERE athlete_id IN (SELECT id FROM pilot_athletes WHERE coach_slug = ?)')
            ->execute(array($coachSlug));
    }

    $achievements = isset($data['achievements']) && is_array($data['achievements']) ? $data['achievements'] : array();
    $insertAch = $pdo->prepare('
        INSERT INTO pilot_achievements (athlete_id, achievement_id, granted_at) VALUES (?, ?, ?)
    ');
    foreach ($achievements as $personName => $items) {
        if (!is_array($items)) {
            continue;
        }
        $normName = legion_normalize_person_name($personName);
        $aidStmt->execute(array($coachSlug, $normName));
        $aidRow = $aidStmt->fetch();
        if (!$aidRow) {
            continue;
        }
        $athleteId = (int) $aidRow['id'];
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['id'])) {
                continue;
            }
            $granted = isset($item['date']) ? (string) $item['date'] : date('Y-m-d');
            $insertAch->execute(array($athleteId, (string) $item['id'], $granted));
        }
    }

    if ($touchUpdatedAt) {
        $ruNow = legion_pilot_db_format_ru_datetime($now);
        legion_pilot_db_meta_set($pdo, 'updated_at', $ruNow, $coachSlug);
        $data['updatedAt'] = $ruNow;
    }
}

function legion_pilot_db_parse_ru_datetime($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return legion_pilot_db_now_sql();
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
        $ts = strtotime($value);
        return $ts !== false ? date('Y-m-d H:i:s', $ts) : legion_pilot_db_now_sql();
    }
    $ts = strtotime(str_replace(',', '', $value));
    return $ts !== false ? date('Y-m-d H:i:s', $ts) : legion_pilot_db_now_sql();
}

function legion_pilot_db_save_dataset(array $data, $throwOnError = true) {
    $pdo = legion_pilot_db_pdo();
    if (!$pdo) {
        if ($throwOnError) {
            throw new RuntimeException('База данных пилотной группы недоступна');
        }
        return $data;
    }

    $coachSlug = isset($data['slug']) ? (string) $data['slug'] : 'pilot-demo';
    $data['slug'] = $coachSlug;
    try {
        $pdo->beginTransaction();
        legion_pilot_db_write_dataset($pdo, $data, true, $coachSlug);
        legion_pilot_db_meta_set($pdo, 'schema_version', (string) LEGION_PILOT_DB_SCHEMA_VERSION);
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($throwOnError) {
            throw new RuntimeException('Не удалось сохранить данные в БД: ' . $e->getMessage());
        }
    }
    return $data;
}

function legion_pilot_db_status() {
    $cfg = legion_pilot_db_config();
    $pdo = legion_pilot_db_pdo();
    $status = array(
        'enabled' => $pdo instanceof PDO,
        'driver' => $cfg['driver'],
        'storage' => legion_pilot_db_storage_label(),
    );
    if ($cfg['driver'] === 'sqlite') {
        $status['path'] = $cfg['path'];
        $status['fileExists'] = is_file($cfg['path']);
        $status['writable'] = is_writable(dirname($cfg['path']));
    }
    if ($pdo) {
        $status['athletes'] = legion_pilot_db_athlete_count($pdo);
        $status['updatedAt'] = legion_pilot_db_meta_get($pdo, 'updated_at', '');
        $status['migratedFromJson'] = legion_pilot_db_meta_get($pdo, 'migrated_from_json', '') !== '';
        $status['rankHistoryMigrated'] = legion_pilot_db_meta_get($pdo, 'rank_history_migrated_from_json', '') !== '';
    }
    return $status;
}

function legion_pilot_db_new_rank_history_id() {
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes(8));
    }
    return uniqid('rh', true);
}

function legion_pilot_db_rank_history_entry_from_row(array $row) {
    $entry = array(
        'id' => $row['id'],
        'date' => legion_pilot_db_format_ru_datetime($row['created_at']),
        'name' => legion_normalize_person_name($row['name']),
        'event' => (string) $row['event'],
    );
    if ($row['mark_index'] !== null && $row['mark_index'] !== '') {
        $entry['markIndex'] = (int) $row['mark_index'];
    }
    if ($row['old_val'] !== null && $row['old_val'] !== '') {
        $entry['oldVal'] = (int) $row['old_val'];
    }
    if ($row['new_val'] !== null && $row['new_val'] !== '') {
        $entry['newVal'] = (int) $row['new_val'];
    }
    return $entry;
}

function legion_pilot_db_load_all_rank_history() {
    $pdo = legion_pilot_db_pdo();
    if (!$pdo) {
        return array();
    }
    if (!function_exists('legion_normalize_person_name')) {
        require_once __DIR__ . '/ranks_lib.php';
    }

    legion_pilot_db_ensure_rank_history_migrated();

    $stmt = $pdo->query('
        SELECT rh.id, rh.event, rh.mark_index, rh.old_val, rh.new_val, rh.created_at, a.name
        FROM pilot_rank_history rh
        INNER JOIN pilot_athletes a ON a.id = rh.athlete_id
        ORDER BY rh.created_at ASC, rh.id ASC
    ');
    $out = array();
    while ($row = $stmt->fetch()) {
        $out[] = legion_pilot_db_rank_history_entry_from_row($row);
    }
    return $out;
}

function legion_pilot_db_find_athlete_id_by_name(PDO $pdo, $name) {
    if (!function_exists('legion_normalize_person_name')) {
        require_once __DIR__ . '/ranks_lib.php';
    }
    $normName = legion_normalize_person_name($name);
    if ($normName === '') {
        return 0;
    }
    $stmt = $pdo->prepare('SELECT id FROM pilot_athletes WHERE name = ? ORDER BY id ASC LIMIT 1');
    $stmt->execute(array($normName));
    $row = $stmt->fetch();
    return $row ? (int) $row['id'] : 0;
}

function legion_pilot_db_trim_rank_history_for_athlete(PDO $pdo, $athleteId, $limit = LEGION_PILOT_RANK_HISTORY_PER_ATHLETE) {
    $athleteId = (int) $athleteId;
    $limit = (int) $limit;
    if ($athleteId <= 0 || $limit <= 0) {
        return;
    }

    $driver = legion_pilot_db_config()['driver'];
    if ($driver === 'mysql') {
        // MySQL не принимает плейсхолдер в OFFSET — значение вшиваем как int.
        $stmt = $pdo->prepare('
            SELECT id FROM pilot_rank_history
            WHERE athlete_id = ?
            ORDER BY created_at DESC, id DESC
            LIMIT 18446744073709551615 OFFSET ' . $limit . '
        ');
        $stmt->execute(array($athleteId));
    } else {
        $stmt = $pdo->prepare('
            SELECT id FROM pilot_rank_history
            WHERE athlete_id = ?
            ORDER BY created_at DESC, id DESC
            LIMIT -1 OFFSET ?
        ');
        $stmt->execute(array($athleteId, $limit));
    }

    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($ids)) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $del = $pdo->prepare('DELETE FROM pilot_rank_history WHERE id IN (' . $placeholders . ')');
    $del->execute($ids);
}

function legion_pilot_db_append_rank_history_entries(array $entries) {
    if (count($entries) === 0) {
        return 0;
    }

    $pdo = legion_pilot_db_pdo();
    if (!$pdo) {
        return 0;
    }
    legion_pilot_db_ensure_rank_history_migrated();
    if (!function_exists('legion_normalize_person_name')) {
        require_once __DIR__ . '/ranks_lib.php';
    }

    $insert = $pdo->prepare('
        INSERT INTO pilot_rank_history (id, athlete_id, event, mark_index, old_val, new_val, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $now = legion_pilot_db_now_sql();
    $affectedAthletes = array();
    $inserted = 0;

    foreach ($entries as $entry) {
        if (!is_array($entry) || empty($entry['name']) || empty($entry['event'])) {
            continue;
        }
        $athleteId = legion_pilot_db_find_athlete_id_by_name($pdo, $entry['name']);
        if ($athleteId <= 0) {
            continue;
        }
        $createdAt = isset($entry['date']) ? legion_pilot_db_parse_ru_datetime($entry['date']) : $now;
        $markIndex = isset($entry['markIndex']) ? (int) $entry['markIndex'] : null;
        if ($markIndex !== null && ($markIndex < 0 || $markIndex >= 60)) {
            $markIndex = null;
        }
        $oldVal = isset($entry['oldVal']) ? (int) $entry['oldVal'] : null;
        $newVal = isset($entry['newVal']) ? (int) $entry['newVal'] : null;
        $insert->execute(array(
            isset($entry['id']) ? (string) $entry['id'] : legion_pilot_db_new_rank_history_id(),
            $athleteId,
            (string) $entry['event'],
            $markIndex,
            $oldVal,
            $newVal,
            $createdAt,
        ));
        $affectedAthletes[$athleteId] = true;
        $inserted++;
    }

    foreach (array_keys($affectedAthletes) as $athleteId) {
        legion_pilot_db_trim_rank_history_for_athlete($pdo, $athleteId);
    }

    return $inserted;
}

function legion_pilot_db_migrate_rank_history_from_json(PDO $pdo) {
    if (legion_pilot_db_meta_get($pdo, 'rank_history_migrated_from_json', '') !== '') {
        return false;
    }

    // Уже проставленные ранги не переносим в историю — только новые события после деплоя.
    legion_pilot_db_meta_set($pdo, 'rank_history_migrated_from_json', legion_pilot_db_now_sql());
    return false;
}

function legion_pilot_db_ensure_rank_history_migrated() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo = legion_pilot_db_pdo();
    if (!$pdo) {
        return;
    }
    legion_pilot_db_migrate_rank_history_from_json($pdo);
}
