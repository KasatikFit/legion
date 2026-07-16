<?php

require_once __DIR__ . '/coaches_legacy.php';
require_once __DIR__ . '/pilot_db_lib.php';

function legion_coaches_reserved_slugs() {
    return array(
        'admin', 'api', 'athlete', 'css', 'diagnostics', 'images', 'js', 'pilot-demo',
        'rating-info', 'about', 'club', 'coach', 'training', 'public_html',
    );
}

function legion_coach_transliterate_slug_part($text) {
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strtolower')) {
        $text = mb_strtolower($text, 'UTF-8');
    } else {
        $text = strtolower($text);
    }
    $map = array(
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
        'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    );
    $out = '';
    $len = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    for ($i = 0; $i < $len; $i++) {
        $ch = function_exists('mb_substr') ? mb_substr($text, $i, 1, 'UTF-8') : $text[$i];
        if (isset($map[$ch])) {
            $out .= $map[$ch];
            continue;
        }
        if (preg_match('/[a-z0-9]/', $ch)) {
            $out .= $ch;
        } elseif ($ch === ' ' || $ch === '-' || $ch === '_') {
            $out .= '-';
        }
    }
    $out = preg_replace('/-+/', '-', $out);
    return trim($out, '-');
}

function legion_coach_suggest_slug($fullName, PDO $pdo = null) {
    $parts = preg_split('/\s+/u', trim((string) $fullName));
    $parts = array_values(array_filter($parts, function ($p) {
        return trim($p) !== '';
    }));
    $base = '';
    if (count($parts) >= 2) {
        $base = legion_coach_transliterate_slug_part($parts[0]);
        if ($base !== '') {
            $base .= '-';
        }
        $base .= legion_coach_transliterate_slug_part($parts[1]);
    } elseif (count($parts) === 1) {
        $base = legion_coach_transliterate_slug_part($parts[0]);
    }
    $base = preg_replace('/[^a-z0-9\-]/', '', strtolower($base));
    $base = trim(preg_replace('/-+/', '-', $base), '-');
    if ($base === '') {
        $base = 'coach';
    }
    if (in_array($base, legion_coaches_reserved_slugs(), true)) {
        $base .= '-group';
    }
    if (!$pdo instanceof PDO) {
        return $base;
    }
    $slug = $base;
    $n = 2;
    while (legion_coaches_slug_exists($pdo, $slug)) {
        $slug = $base . '-' . $n;
        $n++;
    }
    return $slug;
}

function legion_coaches_normalize_slug_input($slug) {
    $slug = strtolower(trim((string) $slug));
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    $slug = trim(preg_replace('/-+/', '-', $slug), '-');
    if ($slug === '') {
        throw new InvalidArgumentException('Укажите корректный slug (латиница, цифры, дефис)');
    }
    if (in_array($slug, legion_coaches_reserved_slugs(), true)) {
        throw new InvalidArgumentException('Этот slug зарезервирован системой');
    }
    return $slug;
}

function legion_coaches_db_pdo() {
    return legion_pilot_db_pdo();
}

function legion_coaches_ensure_schema(PDO $pdo) {
    $driver = legion_pilot_db_config()['driver'];
    if ($driver === 'mysql') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS legion_coaches (
                slug VARCHAR(64) NOT NULL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                tagline VARCHAR(255) NOT NULL DEFAULT 'Группа тренера',
                storage VARCHAR(16) NOT NULL DEFAULT 'mysql',
                csv_url TEXT NOT NULL,
                ranks_csv_url TEXT NOT NULL,
                is_visible TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS legion_coach_auth (
                coach_slug VARCHAR(64) NOT NULL PRIMARY KEY,
                password_hash VARCHAR(255) NOT NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } else {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS legion_coaches (
                slug TEXT NOT NULL PRIMARY KEY,
                name TEXT NOT NULL,
                tagline TEXT NOT NULL DEFAULT 'Группа тренера',
                storage TEXT NOT NULL DEFAULT 'mysql',
                csv_url TEXT NOT NULL DEFAULT '',
                ranks_csv_url TEXT NOT NULL DEFAULT '',
                is_visible INTEGER NOT NULL DEFAULT 1,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS legion_coach_auth (
                coach_slug TEXT NOT NULL PRIMARY KEY,
                password_hash TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )
        ");
    }
}

function legion_coaches_row_to_config(array $row) {
    return array(
        'name' => (string) $row['name'],
        'tagline' => (string) $row['tagline'],
        'storage' => (string) $row['storage'],
        'csvUrl' => (string) $row['csv_url'],
        'ranksCsvUrl' => (string) $row['ranks_csv_url'],
        'isVisible' => ((int) $row['is_visible']) > 0,
        'sortOrder' => (int) $row['sort_order'],
    );
}

function legion_coaches_slug_exists(PDO $pdo, $slug) {
    $stmt = $pdo->prepare('SELECT slug FROM legion_coaches WHERE slug = ? LIMIT 1');
    $stmt->execute(array($slug));
    return (bool) $stmt->fetchColumn();
}

function legion_coaches_seed_from_legacy(PDO $pdo) {
    $stmt = $pdo->query('SELECT COUNT(*) FROM legion_coaches');
    if ($stmt && (int) $stmt->fetchColumn() > 0) {
        return 0;
    }
    $legacy = legion_coaches_legacy_config();
    $now = legion_pilot_db_now_sql();
    $order = 0;
    $insert = $pdo->prepare('
        INSERT INTO legion_coaches
            (slug, name, tagline, storage, csv_url, ranks_csv_url, is_visible, sort_order, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)
    ');
    foreach ($legacy as $slug => $coach) {
        $order += 10;
        $insert->execute(array(
            $slug,
            $coach['name'],
            isset($coach['tagline']) ? $coach['tagline'] : 'Группа тренера',
            isset($coach['storage']) ? $coach['storage'] : 'mysql',
            isset($coach['csvUrl']) ? $coach['csvUrl'] : '',
            isset($coach['ranksCsvUrl']) ? $coach['ranksCsvUrl'] : '',
            $order,
            $now,
            $now,
        ));
    }
    return count($legacy);
}

function legion_coaches_load_registry_from_db($forceReload = false) {
    static $cache = null;
    if ($forceReload === true) {
        $cache = null;
    }
    if (is_array($cache)) {
        return $cache;
    }
    $cache = array();
    $pdo = legion_coaches_db_pdo();
    if (!$pdo instanceof PDO) {
        $cache = legion_coaches_legacy_config();
        return $cache;
    }
    try {
        legion_coaches_ensure_schema($pdo);
        legion_coaches_seed_from_legacy($pdo);
        $stmt = $pdo->query('
            SELECT slug, name, tagline, storage, csv_url, ranks_csv_url, is_visible, sort_order
            FROM legion_coaches
            ORDER BY sort_order ASC, name ASC
        ');
        while ($row = $stmt->fetch()) {
            $slug = (string) $row['slug'];
            $cfg = legion_coaches_row_to_config($row);
            $cache[$slug] = array(
                'name' => $cfg['name'],
                'tagline' => $cfg['tagline'],
                'storage' => $cfg['storage'],
                'csvUrl' => $cfg['csvUrl'],
                'ranksCsvUrl' => $cfg['ranksCsvUrl'],
                '_isVisible' => $cfg['isVisible'],
                '_sortOrder' => $cfg['sortOrder'],
            );
        }
    } catch (Exception $e) {
        $cache = legion_coaches_legacy_config();
    }
    return $cache;
}

function legion_coaches_registry() {
    $all = legion_coaches_load_registry_from_db();
    $out = array();
    foreach ($all as $slug => $coach) {
        $item = $coach;
        unset($item['_isVisible'], $item['_sortOrder']);
        $out[$slug] = $item;
    }
    return $out;
}

function legion_coaches_config() {
    $all = legion_coaches_load_registry_from_db();
    $visible = array();
    foreach ($all as $slug => $coach) {
        $isVisible = !isset($coach['_isVisible']) || $coach['_isVisible'];
        if (!$isVisible) {
            continue;
        }
        $item = $coach;
        unset($item['_isVisible'], $item['_sortOrder']);
        $visible[$slug] = $item;
    }
    return $visible;
}

function legion_coaches_has_training_password($slug) {
    if (legion_coach_auth_hash_from_db($slug) !== '') {
        return true;
    }
    $path = __DIR__ . '/coach_auth.php';
    if (!is_file($path)) {
        return false;
    }
    $map = require $path;
    if (!is_array($map) || !isset($map[$slug]) || !is_array($map[$slug])) {
        return false;
    }
    $entry = $map[$slug];
    return !empty($entry['password_hash']) || !empty($entry['password']);
}

function legion_coaches_admin_list() {
    $pdo = legion_coaches_db_pdo();
    if (!$pdo instanceof PDO) {
        $legacy = legion_coaches_legacy_config();
        $list = array();
        $order = 0;
        foreach ($legacy as $slug => $coach) {
            $order += 10;
            $list[] = array(
                'slug' => $slug,
                'name' => $coach['name'],
                'tagline' => isset($coach['tagline']) ? $coach['tagline'] : 'Группа тренера',
                'storage' => isset($coach['storage']) ? $coach['storage'] : 'mysql',
                'csvUrl' => isset($coach['csvUrl']) ? $coach['csvUrl'] : '',
                'ranksCsvUrl' => isset($coach['ranksCsvUrl']) ? $coach['ranksCsvUrl'] : '',
                'isVisible' => true,
                'sortOrder' => $order,
                'hasTrainingPassword' => null,
                'ratingUrl' => '/' . $slug . '/',
                'trainingUrl' => '/' . $slug . '/training.php',
            );
        }
        return $list;
    }
    legion_coaches_ensure_schema($pdo);
    legion_coaches_seed_from_legacy($pdo);
    $stmt = $pdo->query('
        SELECT slug, name, tagline, storage, csv_url, ranks_csv_url, is_visible, sort_order
        FROM legion_coaches
        ORDER BY sort_order ASC, name ASC
    ');
    $list = array();
    while ($row = $stmt->fetch()) {
        $slug = (string) $row['slug'];
        $list[] = array(
            'slug' => $slug,
            'name' => (string) $row['name'],
            'tagline' => (string) $row['tagline'],
            'storage' => (string) $row['storage'],
            'csvUrl' => (string) $row['csv_url'],
            'ranksCsvUrl' => (string) $row['ranks_csv_url'],
            'isVisible' => ((int) $row['is_visible']) > 0,
            'sortOrder' => (int) $row['sort_order'],
            'hasTrainingPassword' => legion_coaches_has_training_password($slug),
            'ratingUrl' => '/' . $slug . '/',
            'trainingUrl' => '/' . $slug . '/training.php',
        );
    }
    return $list;
}

function legion_coaches_public_root() {
    return dirname(__DIR__);
}

function legion_coach_provision_files($slug) {
    $slug = legion_coaches_normalize_slug_input($slug);
    $root = legion_coaches_public_root();
    $dir = $root . '/' . $slug;
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Не удалось создать каталог /' . $slug . '/');
        }
    }
    $indexPhp = '<?php
$LEGION_COACH_SLUG = basename(__DIR__);
$GLOBALS[\'LEGION_COACH_SLUG\'] = $LEGION_COACH_SLUG;
$LEGION_PAGE = \'coach\';
$coachPage = dirname(__DIR__) . \'/coach-page.php\';
if (!is_file($coachPage)) {
    http_response_code(500);
    header(\'Content-Type: text/html; charset=utf-8\');
    echo \'<h1>Ошибка 500</h1><p>Не найден файл coach-page.php.</p>\';
    exit;
}
require $coachPage;
';
    $trainingPhp = '<?php
$LEGION_COACH_SLUG = basename(__DIR__);
$GLOBALS[\'LEGION_COACH_SLUG\'] = $LEGION_COACH_SLUG;
require dirname(__DIR__) . \'/training-page.php\';
';
    $indexPath = $dir . '/index.php';
    $trainingPath = $dir . '/training.php';
    if (!is_file($indexPath)) {
        if (file_put_contents($indexPath, $indexPhp) === false) {
            throw new RuntimeException('Не удалось создать ' . $slug . '/index.php');
        }
    }
    if (!is_file($trainingPath)) {
        if (file_put_contents($trainingPath, $trainingPhp) === false) {
            throw new RuntimeException('Не удалось создать ' . $slug . '/training.php');
        }
    }
    $photosDir = $root . '/images/coach-athletes/' . $slug;
    if (!is_dir($photosDir)) {
        @mkdir($photosDir, 0755, true);
    }
    return array(
        'slugDir' => $dir,
        'photosDir' => $photosDir,
        'indexPath' => $indexPath,
        'trainingPath' => $trainingPath,
    );
}

function legion_coach_auth_set_password_db($slug, $password) {
    $pdo = legion_coaches_db_pdo();
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('База данных недоступна');
    }
    legion_coaches_ensure_schema($pdo);
    $password = (string) $password;
    if (strlen($password) < 4) {
        throw new InvalidArgumentException('Пароль должен быть не короче 4 символов');
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $now = legion_pilot_db_now_sql();
    $driver = legion_pilot_db_config()['driver'];
    if ($driver === 'mysql') {
        $stmt = $pdo->prepare('
            INSERT INTO legion_coach_auth (coach_slug, password_hash, updated_at)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), updated_at = VALUES(updated_at)
        ');
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO legion_coach_auth (coach_slug, password_hash, updated_at)
            VALUES (?, ?, ?)
            ON CONFLICT(coach_slug) DO UPDATE SET password_hash = excluded.password_hash, updated_at = excluded.updated_at
        ');
    }
    $stmt->execute(array($slug, $hash, $now));
}

function legion_coach_auth_hash_from_db($slug) {
    $pdo = legion_coaches_db_pdo();
    if (!$pdo instanceof PDO) {
        return '';
    }
    try {
        legion_coaches_ensure_schema($pdo);
        $stmt = $pdo->prepare('SELECT password_hash FROM legion_coach_auth WHERE coach_slug = ? LIMIT 1');
        $stmt->execute(array($slug));
        $hash = $stmt->fetchColumn();
        return $hash ? (string) $hash : '';
    } catch (Exception $e) {
        return '';
    }
}

function legion_coaches_create($name, $password, $options = array()) {
    $name = trim((string) $name);
    if ($name === '') {
        throw new InvalidArgumentException('Укажите фамилию и имя тренера');
    }
    $pdo = legion_coaches_db_pdo();
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('MySQL недоступен — настройте pilot_db_config.php');
    }
    legion_coaches_ensure_schema($pdo);
    legion_coaches_seed_from_legacy($pdo);

    $opts = is_array($options) ? $options : array();
    $slug = isset($opts['slug']) && trim((string) $opts['slug']) !== ''
        ? legion_coaches_normalize_slug_input($opts['slug'])
        : legion_coach_suggest_slug($name, $pdo);
    if (legion_coaches_slug_exists($pdo, $slug)) {
        throw new InvalidArgumentException('Тренер с таким slug уже есть: ' . $slug);
    }

    $tagline = isset($opts['tagline']) ? trim((string) $opts['tagline']) : 'Группа тренера';
    if ($tagline === '') {
        $tagline = 'Группа тренера';
    }
    $storage = isset($opts['storage']) ? trim((string) $opts['storage']) : 'mysql';
    if ($storage === '') {
        $storage = 'mysql';
    }
    $csvUrl = isset($opts['csvUrl']) ? trim((string) $opts['csvUrl']) : '';
    $ranksCsvUrl = isset($opts['ranksCsvUrl']) ? trim((string) $opts['ranksCsvUrl']) : '';

    $maxOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM legion_coaches')->fetchColumn();
    $now = legion_pilot_db_now_sql();

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('
            INSERT INTO legion_coaches
                (slug, name, tagline, storage, csv_url, ranks_csv_url, is_visible, sort_order, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)
        ');
        $stmt->execute(array(
            $slug, $name, $tagline, $storage, $csvUrl, $ranksCsvUrl, $maxOrder + 10, $now, $now,
        ));
        legion_coach_auth_set_password_db($slug, $password);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    $files = legion_coach_provision_files($slug);
    legion_coaches_invalidate_cache();

    return array(
        'slug' => $slug,
        'name' => $name,
        'ratingUrl' => '/' . $slug . '/',
        'trainingUrl' => '/' . $slug . '/training.php',
        'files' => $files,
    );
}

function legion_coaches_update($slug, array $fields) {
    $slug = legion_coaches_normalize_slug_input($slug);
    $pdo = legion_coaches_db_pdo();
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('MySQL недоступен');
    }
    if (!legion_coaches_slug_exists($pdo, $slug)) {
        throw new InvalidArgumentException('Тренер не найден');
    }

    $sets = array();
    $params = array();
    if (isset($fields['name'])) {
        $name = trim((string) $fields['name']);
        if ($name === '') {
            throw new InvalidArgumentException('Имя не может быть пустым');
        }
        $sets[] = 'name = ?';
        $params[] = $name;
    }
    if (isset($fields['tagline'])) {
        $sets[] = 'tagline = ?';
        $params[] = trim((string) $fields['tagline']);
    }
    if (array_key_exists('isVisible', $fields)) {
        $sets[] = 'is_visible = ?';
        $params[] = !empty($fields['isVisible']) ? 1 : 0;
    }
    if (isset($fields['password']) && trim((string) $fields['password']) !== '') {
        legion_coach_auth_set_password_db($slug, (string) $fields['password']);
    }
    if (!empty($sets)) {
        $sets[] = 'updated_at = ?';
        $params[] = legion_pilot_db_now_sql();
        $params[] = $slug;
        $sql = 'UPDATE legion_coaches SET ' . implode(', ', $sets) . ' WHERE slug = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
    legion_coaches_invalidate_cache();
    return true;
}

function legion_coaches_invalidate_cache() {
    legion_coaches_load_registry_from_db(true);
}
