<?php

require_once __DIR__ . '/coaches.php';
require_once dirname(__DIR__) . '/legion-version.php';

function legion_diagnostics_exercises() {
    return array(
        array('key' => 'push', 'label' => 'Отжимания', 'match' => 'отжимания'),
        array('key' => 'pull', 'label' => 'Подтягивания', 'match' => 'подтягивания'),
        array('key' => 'hang', 'label' => 'Вис (сек)', 'match' => 'вис'),
        array('key' => 'burpee', 'label' => 'Бёрпи за 1 мин', 'match' => 'бёрпи'),
        array('key' => 'crunch', 'label' => 'Скручивания', 'match' => 'скручиван'),
        array('key' => 'jump', 'label' => 'Прыжок в длину (см)', 'match' => 'прыжок'),
    );
}

function legion_diagnostics_fetch_url($url, $timeoutSec = 20) {
    if (!function_exists('curl_init')) {
        $ctx = stream_context_create(array(
            'http' => array('timeout' => $timeoutSec, 'ignore_errors' => true),
        ));
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            return array('ok' => false, 'error' => 'Не удалось загрузить URL');
        }
        return array('ok' => true, 'body' => $body, 'httpCode' => 200);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => $timeoutSec,
        CURLOPT_TIMEOUT => $timeoutSec,
        CURLOPT_SSL_VERIFYPEER => true,
    ));
    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return array('ok' => false, 'error' => $err ?: 'Ошибка cURL');
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        return array('ok' => false, 'error' => 'HTTP ' . $httpCode);
    }
    return array('ok' => true, 'body' => $body, 'httpCode' => $httpCode);
}

function legion_diagnostics_validate_csv($text) {
    $issues = array();
    $lines = preg_split('/\r\n|\r|\n/', trim($text));
    if (count($lines) < 2) {
        return array(
            'ok' => false,
            'athletes' => 0,
            'issues' => array('Таблица пуста или содержит только заголовок'),
            'headers' => array(),
        );
    }

    $headers = array_map('trim', explode(',', $lines[0]));
    $lower = array_map(function ($h) {
        return function_exists('mb_strtolower') ? mb_strtolower($h, 'UTF-8') : strtolower($h);
    }, $headers);

    $nameIdx = -1;
    foreach ($lower as $i => $h) {
        if (strpos($h, 'фио') !== false || strpos($h, 'имя') !== false) {
            $nameIdx = $i;
            break;
        }
    }
    if ($nameIdx === -1) {
        $issues[] = 'Нет столбца с ФИО / именем';
    }

    $exercises = legion_diagnostics_exercises();
    $colIdx = array();
    foreach ($exercises as $ex) {
        $idx = -1;
        foreach ($lower as $i => $h) {
            if (strpos($h, $ex['match']) !== false) {
                $idx = $i;
                break;
            }
        }
        $colIdx[$ex['key']] = $idx;
        if ($idx === -1) {
            $issues[] = 'Нет столбца: ' . $ex['label'];
        }
    }

    $athletes = 0;
    if ($nameIdx >= 0 && empty($issues)) {
        $minCols = max(array_merge(array($nameIdx), array_values($colIdx))) + 1;
        for ($i = 1; $i < count($lines); $i++) {
            $cols = array_map('trim', explode(',', $lines[$i]));
            if (count($cols) < $minCols) {
                continue;
            }
            $name = $cols[$nameIdx];
            if ($name === '') {
                continue;
            }
            $valid = true;
            foreach ($exercises as $ex) {
                $val = $cols[$colIdx[$ex['key']]];
                if ($val === '' || !is_numeric($val)) {
                    $valid = false;
                    break;
                }
            }
            if ($valid) {
                $athletes++;
            }
        }
        if ($athletes === 0) {
            $issues[] = 'Нет строк с полными числовыми результатами';
        }
    }

    return array(
        'ok' => empty($issues),
        'athletes' => $athletes,
        'issues' => $issues,
        'headers' => $headers,
    );
}

function legion_diagnostics_check_coach_table($slug, $coach) {
    $result = array(
        'slug' => $slug,
        'name' => $coach['name'],
        'status' => 'error',
        'message' => '',
        'athletes' => 0,
        'issues' => array(),
    );

    if (empty($coach['csvUrl'])) {
        $result['message'] = 'Не задан csvUrl';
        return $result;
    }

    $fetch = legion_diagnostics_fetch_url($coach['csvUrl']);
    if (!$fetch['ok']) {
        $result['message'] = $fetch['error'];
        return $result;
    }

    $csv = legion_diagnostics_validate_csv($fetch['body']);
    $result['athletes'] = $csv['athletes'];
    $result['issues'] = $csv['issues'];

    if ($csv['ok']) {
        $result['status'] = 'ok';
        $result['message'] = 'OK — ' . $csv['athletes'] . ' спортсменов';
    } else {
        $result['message'] = implode('; ', $csv['issues']);
    }

    return $result;
}

function legion_diagnostics_validate_ranks_csv($text) {
    $issues = array();
    $lines = preg_split('/\r\n|\r|\n/', trim($text));
    if (count($lines) < 2) {
        return array(
            'ok' => false,
            'athletes' => 0,
            'issues' => array('Таблица пуста или содержит только заголовок'),
        );
    }

    $headers = array_map('trim', explode(',', $lines[0]));
    $lower = array_map(function ($h) {
        return function_exists('mb_strtolower') ? mb_strtolower($h, 'UTF-8') : strtolower($h);
    }, $headers);

    $nameIdx = -1;
    foreach ($lower as $i => $h) {
        if (strpos($h, 'фио') !== false || strpos($h, 'имя') !== false) {
            $nameIdx = $i;
            break;
        }
    }
    if ($nameIdx === -1) {
        $issues[] = 'Нет столбца с ФИО / именем';
    }

    $normCount = max(0, count($headers) - 1);
    if ($normCount < 60) {
        $issues[] = 'Мало столбцов нормативов (нужно 60, найдено ' . $normCount . ')';
    }

    $athletes = 0;
    if ($nameIdx >= 0) {
        for ($i = 1; $i < count($lines); $i++) {
            $cols = array_map('trim', explode(',', $lines[$i]));
            if (!isset($cols[$nameIdx]) || $cols[$nameIdx] === '') {
                continue;
            }
            $athletes++;
        }
        if ($athletes === 0) {
            $issues[] = 'Нет строк со спортсменами';
        }
    }

    return array(
        'ok' => empty($issues),
        'athletes' => $athletes,
        'issues' => $issues,
        'normColumns' => $normCount,
    );
}

function legion_diagnostics_mysql_required_tables() {
    return array(
        'pilot_meta',
        'pilot_athletes',
        'pilot_results',
        'pilot_rank_marks',
        'pilot_history',
        'pilot_rank_history',
        'pilot_achievements',
        'legion_coach_elite',
        'legion_scope_achievements',
        'legion_scope_snapshots',
        'legion_coaches',
        'legion_coach_auth',
    );
}

function legion_diagnostics_mysql_table_exists(PDO $pdo, $driver, $table) {
    if ($driver === 'mysql') {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute(array($table));
        return (bool) $stmt->fetchColumn();
    }
    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
    $stmt->execute(array($table));
    return (bool) $stmt->fetchColumn();
}

function legion_diagnostics_check_mysql() {
    $root = dirname(__DIR__);
    $items = array();
    $configPath = $root . '/api/pilot_db_config.php';

    if (!is_file($configPath)) {
        $items[] = array(
            'name' => 'api/pilot_db_config.php',
            'status' => 'error',
            'detail' => 'не найден — скопируйте из pilot_db_config.example.php',
        );
        return array('items' => $items, 'enabled' => false, 'driver' => '', 'pdo' => null);
    }

    $items[] = array(
        'name' => 'api/pilot_db_config.php',
        'status' => 'ok',
        'detail' => 'найден',
    );

    require_once __DIR__ . '/pilot_db_lib.php';
    $cfg = legion_pilot_db_config();
    $driver = isset($cfg['driver']) ? $cfg['driver'] : 'sqlite';
    $items[] = array(
        'name' => 'Драйвер БД',
        'status' => $driver === 'mysql' ? 'ok' : 'warn',
        'detail' => $driver === 'mysql' ? 'MySQL' : 'SQLite (для продакшена укажите mysql в конфиге)',
    );

    $pdo = legion_pilot_db_pdo();
    if (!$pdo instanceof PDO) {
        $items[] = array(
            'name' => 'Подключение к БД',
            'status' => 'error',
            'detail' => 'не удалось подключиться — проверьте хост, имя базы, логин и пароль',
        );
        return array('items' => $items, 'enabled' => false, 'driver' => $driver, 'pdo' => null);
    }

    $items[] = array(
        'name' => 'Подключение к БД',
        'status' => 'ok',
        'detail' => $driver === 'mysql'
            ? 'соединение с ' . (isset($cfg['dbname']) ? $cfg['dbname'] : 'MySQL') . ' установлено'
            : 'SQLite: ' . (isset($cfg['path']) ? basename($cfg['path']) : 'pilot-demo.sqlite'),
    );

    require_once __DIR__ . '/coaches_lib.php';
    try {
        legion_coaches_ensure_schema($pdo);
    } catch (Exception $e) {
        $items[] = array(
            'name' => 'legion_coaches (схема)',
            'status' => 'error',
            'detail' => $e->getMessage(),
        );
    }

    $missingTables = array();
    foreach (legion_diagnostics_mysql_required_tables() as $table) {
        if (!legion_diagnostics_mysql_table_exists($pdo, $driver, $table)) {
            $missingTables[] = $table;
        }
    }
    if (!empty($missingTables)) {
        $items[] = array(
            'name' => 'Таблицы',
            'status' => 'error',
            'detail' => 'нет таблиц: ' . implode(', ', $missingTables) . ' — выполните api/migrations/pilot_mysql.sql и coaches_mysql.sql',
        );
    } else {
        $items[] = array(
            'name' => 'Таблицы',
            'status' => 'ok',
            'detail' => 'все ' . count(legion_diagnostics_mysql_required_tables()) . ' таблиц на месте',
        );
        try {
            legion_coaches_seed_from_legacy($pdo);
            $coachCount = (int) $pdo->query('SELECT COUNT(*) FROM legion_coaches')->fetchColumn();
            $visibleCount = (int) $pdo->query('SELECT COUNT(*) FROM legion_coaches WHERE is_visible = 1')->fetchColumn();
            $items[] = array(
                'name' => 'Реестр тренеров (legion_coaches)',
                'status' => $coachCount > 0 ? 'ok' : 'warn',
                'detail' => $coachCount . ' в базе, ' . $visibleCount . ' на главной клуба',
            );
            $authDbCount = (int) $pdo->query('SELECT COUNT(*) FROM legion_coach_auth')->fetchColumn();
            $items[] = array(
                'name' => 'Пароли тренировки (legion_coach_auth)',
                'status' => 'ok',
                'detail' => $authDbCount . ' в MySQL; остальные — из api/coach_auth.php',
            );
        } catch (Exception $e) {
            $items[] = array(
                'name' => 'Реестр тренеров',
                'status' => 'error',
                'detail' => $e->getMessage(),
            );
        }
    }

    $coachAuthPath = $root . '/api/coach_auth.php';
    $items[] = array(
        'name' => 'api/coach_auth.php',
        'status' => is_file($coachAuthPath) ? 'ok' : 'warn',
        'detail' => is_file($coachAuthPath)
            ? 'резервные пароли (приоритет у MySQL legion_coach_auth)'
            : 'не найден — пароли только в MySQL или задайте через /admin/',
    );

    $adminAuthPath = $root . '/api/admin_auth.php';
    require_once __DIR__ . '/admin_auth_lib.php';
    $items[] = array(
        'name' => 'api/admin_auth.php',
        'status' => legion_admin_auth_is_configured() ? 'ok' : 'warn',
        'detail' => legion_admin_auth_is_configured()
            ? 'суперадмин настроен — /admin/'
            : 'не найден — скопируйте из admin_auth.example.php',
    );

    $photosRoot = $root . '/images/coach-athletes';
    $photosWritable = is_dir($photosRoot) ? is_writable($photosRoot) : is_writable($root . '/images');
    $items[] = array(
        'name' => 'images/coach-athletes/',
        'status' => $photosWritable ? 'ok' : 'warn',
        'detail' => $photosWritable
            ? 'каталог доступен для загрузки фото'
            : 'нет прав на запись — фото спортсменов не сохранятся',
    );

    return array(
        'items' => $items,
        'enabled' => empty($missingTables),
        'driver' => $driver,
        'pdo' => $pdo,
    );
}

function legion_diagnostics_check_coach_mysql($slug, $coach, PDO $pdo) {
    require_once __DIR__ . '/coach_data_lib.php';

    $root = dirname(__DIR__);
    $result = array(
        'dataStatus' => 'error',
        'dataMessage' => '',
        'trainingStatus' => 'error',
        'trainingMessage' => '',
    );

    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM pilot_athletes WHERE coach_slug = ?');
        $stmt->execute(array($slug));
        $count = (int) $stmt->fetchColumn();

        $updatedAt = '';
        if (function_exists('legion_pilot_db_meta_get')) {
            $updatedAt = legion_pilot_db_meta_get($pdo, 'updated_at', '', $slug);
        }

        if ($count > 0) {
            $result['dataStatus'] = 'ok';
            $result['dataMessage'] = $count . ' спортсменов в MySQL'
                . ($updatedAt !== '' ? ', обновлено ' . $updatedAt : '');
        } else {
            $result['dataStatus'] = 'warn';
            $result['dataMessage'] = 'в MySQL нет спортсменов — импортируйте список в режиме тренировки';
        }
    } catch (Exception $e) {
        $result['dataMessage'] = $e->getMessage();
        return $result;
    }

    $trainingPath = $root . '/' . $slug . '/training.php';
    if (!is_file($trainingPath)) {
        $result['trainingMessage'] = 'нет ' . $slug . '/training.php';
        return $result;
    }

    $photosDir = legion_coach_photos_dir($slug);
    if (!is_dir($photosDir)) {
        @mkdir($photosDir, 0755, true);
    }
    if (!is_dir($photosDir) || !is_writable($photosDir)) {
        $result['trainingStatus'] = 'warn';
        $result['trainingMessage'] = 'training.php есть, но нет прав на каталог фото';
        return $result;
    }

    $result['trainingStatus'] = 'ok';
    $result['trainingMessage'] = 'режим тренировки и каталог фото готовы';

    return $result;
}

function legion_diagnostics_check_coach_ranks($slug, $coach) {
    require_once __DIR__ . '/ranks_lib.php';

    $result = array(
        'slug' => $slug,
        'name' => $coach['name'],
        'status' => 'error',
        'message' => '',
        'athletes' => 0,
    );

    if (empty($coach['ranksCsvUrl'])) {
        $result['message'] = 'Не задан ranksCsvUrl';
        return $result;
    }

    $fetch = legion_diagnostics_fetch_url($coach['ranksCsvUrl']);
    if (!$fetch['ok']) {
        $result['message'] = $fetch['error'];
        return $result;
    }

    $entries = legion_parse_rank_csv_entries($fetch['body']);
    $coachNames = legion_get_coach_result_names($coach);
    $merged = legion_merge_coach_rank_entries($entries, $coachNames);
    $withMarks = 0;
    foreach ($merged as $marks) {
        $result['athletes']++;
        if (legion_count_rank_marks($marks) > 0) {
            $withMarks++;
        }
    }

    if ($withMarks > 0) {
        $result['status'] = 'ok';
        $result['message'] = 'OK — ' . $withMarks . ' спортсменов с отметками рангов';
    } elseif (count($entries) > 0) {
        $result['status'] = 'warn';
        $result['message'] = 'Отметки есть, но ФИО не сопоставились с таблицей результатов';
    } else {
        $result['message'] = 'Нет строк с отметками в таблице рангов';
    }

    return $result;
}

function legion_diagnostics_run() {
    $root = dirname(__DIR__);
    $checks = array();

    $checks[] = array(
        'group' => 'Версия',
        'items' => array(
            array(
                'name' => 'Версия статики (legion-version.php)',
                'status' => 'ok',
                'detail' => 'v' . legion_asset_version(),
            ),
        ),
    );

    $requiredFiles = array(
        'club-page.php',
        'coach-page.php',
        'js/legion-config.js',
        'js/legion-core.js',
        'js/legion-club.js',
        'js/legion-coach.js',
        'js/legion-ui.js',
        'css/legion.css',
        'api/coaches.php',
        'api/coaches_lib.php',
        'api/coaches_legacy.php',
        'api/admin_auth_lib.php',
        'admin/index.php',
        'api/get_elite.php',
        'api/save_elite.php',
        'api/verify_rotation_password.php',
        'api/diagnostics_lib.php',
        'api/ranks_lib.php',
        'api/get_ranks.php',
        'api/get_page_data.php',
        'api/page_data_lib.php',
        'api/pilot_db_lib.php',
        'training-page.php',
        'diagnostics/index.php',
    );

    $fileItems = array();
    foreach ($requiredFiles as $rel) {
        $path = $root . '/' . $rel;
        $fileItems[] = array(
            'name' => $rel,
            'status' => is_file($path) ? 'ok' : 'error',
            'detail' => is_file($path) ? 'найден' : 'отсутствует на сервере',
        );
    }
    $checks[] = array('group' => 'Файлы', 'items' => $fileItems);

    $mysqlReport = legion_diagnostics_check_mysql();
    $checks[] = array('group' => 'MySQL', 'items' => $mysqlReport['items']);

    $rotationPath = $root . '/api/rotation_config.php';
    $checks[] = array(
        'group' => 'Ротация элиты',
        'items' => array(
            array(
                'name' => 'api/rotation_config.php',
                'status' => is_file($rotationPath) ? 'ok' : 'warn',
                'detail' => is_file($rotationPath) ? 'настроен' : 'не найден — ручная ротация не работает',
            ),
        ),
    );

    $apiDir = $root . '/api';
    $jsonFiles = array('elite.json', 'history.json', 'rank_history.json', 'last_ranks.json', 'achievements.json');
    $jsonItems = array();
    foreach ($jsonFiles as $file) {
        $path = $apiDir . '/' . $file;
        $exists = is_file($path);
        $writable = is_writable($apiDir) && (!$exists || is_writable($path));
        $status = $writable ? 'ok' : ($exists ? 'warn' : 'warn');
        $detail = $exists
            ? ($writable ? 'есть, запись разрешена' : 'есть, но нет прав на запись')
            : ($writable ? 'создастся при первом сохранении' : 'каталог api/ недоступен для записи');
        $jsonItems[] = array(
            'name' => 'api/' . $file,
            'status' => $status,
            'detail' => $detail,
        );
    }
    $checks[] = array('group' => 'Данные API', 'items' => $jsonItems);

    require_once __DIR__ . '/sheets_cache_lib.php';
    legion_sheets_cache_ensure_dir();
    $cacheDir = LEGION_SHEETS_CACHE_DIR;
    $cacheWritable = is_dir($cacheDir) && is_writable($cacheDir);
    $cacheFiles = is_dir($cacheDir) ? glob($cacheDir . '/*.json') : array();
    $checks[] = array(
        'group' => 'Кэш Google Таблиц',
        'items' => array(
            array(
                'name' => 'api/cache/sheets/',
                'status' => $cacheWritable ? 'ok' : 'error',
                'detail' => $cacheWritable
                    ? 'запись разрешена, файлов: ' . count($cacheFiles)
                    : 'нет каталога или нет прав — каждый визит ждёт Google',
            ),
            array(
                'name' => 'TTL кэша таблиц',
                'status' => 'ok',
                'detail' => LEGION_SHEETS_CACHE_TTL . ' сек (свежий), до ' . LEGION_SHEETS_CACHE_STALE_MAX . ' сек при сбое Google',
            ),
        ),
    );

    $coaches = legion_coaches_config();
    $coachItems = array();
    $mysqlPdo = ($mysqlReport['enabled'] && $mysqlReport['pdo'] instanceof PDO) ? $mysqlReport['pdo'] : null;
    $allCoachesMysql = true;

    foreach ($coaches as $slug => $coach) {
        if (!legion_coach_uses_mysql($slug)) {
            $allCoachesMysql = false;
        }

        $pagePath = $root . '/' . $slug . '/index.php';
        if (!is_file($pagePath)) {
            $coachItems[] = array(
                'name' => $coach['name'] . ' — страница /' . $slug . '/',
                'status' => 'error',
                'detail' => 'нет ' . $slug . '/index.php',
            );
            continue;
        }

        if (legion_coach_uses_mysql($slug)) {
            if (!$mysqlPdo) {
                $coachItems[] = array(
                    'name' => $coach['name'] . ' — MySQL',
                    'status' => 'error',
                    'detail' => 'база недоступна — см. раздел MySQL выше',
                );
                continue;
            }

            $mysqlCoach = legion_diagnostics_check_coach_mysql($slug, $coach, $mysqlPdo);
            $coachItems[] = array(
                'name' => $coach['name'] . ' — данные',
                'status' => $mysqlCoach['dataStatus'],
                'detail' => $mysqlCoach['dataMessage'],
            );
            $coachItems[] = array(
                'name' => $coach['name'] . ' — тренировка',
                'status' => $mysqlCoach['trainingStatus'],
                'detail' => $mysqlCoach['trainingMessage'],
            );
            continue;
        }

        $table = legion_diagnostics_check_coach_table($slug, $coach);
        $coachItems[] = array(
            'name' => $coach['name'] . ' — результаты (Google)',
            'status' => $table['status'],
            'detail' => $table['message'],
        );

        $ranks = legion_diagnostics_check_coach_ranks($slug, $coach);
        $coachItems[] = array(
            'name' => $coach['name'] . ' — ранги (Google)',
            'status' => $ranks['status'],
            'detail' => $ranks['message'],
        );
    }
    $checks[] = array('group' => 'Тренеры', 'items' => $coachItems);

    if ($allCoachesMysql && $mysqlReport['enabled']) {
        require_once __DIR__ . '/page_data_lib.php';
        $clubPayload = legion_build_club_page_data_from_mysql();
        $athleteCount = count($clubPayload['athletes']);
        $checks[] = array(
            'group' => 'Клубный рейтинг',
            'items' => array(
                array(
                    'name' => 'api/get_page_data.php (MySQL)',
                    'status' => $athleteCount > 0 ? 'ok' : 'warn',
                    'detail' => $athleteCount > 0
                        ? 'собрано ' . $athleteCount . ' спортсменов из ' . (int) $clubPayload['loaded'] . ' групп'
                        : 'нет спортсменов в базе — импортируйте данные тренеров',
                ),
            ),
        );
    } else {
        require_once __DIR__ . '/ranks_lib.php';
        $allRanks = legion_load_all_ranks();
        $checks[] = array(
            'group' => 'API рангов',
            'items' => array(
                array(
                    'name' => 'api/get_ranks.php',
                    'status' => $allRanks['loaded'] > 0 ? 'ok' : 'error',
                    'detail' => $allRanks['loaded'] > 0
                        ? 'загружено ' . $allRanks['loaded'] . ' спортсменов с рангами из Google Таблиц'
                        : 'ни одного ранга не сопоставлено — проверьте ФИО в таблицах',
                ),
            ),
        );
    }

    $summary = array('ok' => 0, 'warn' => 0, 'error' => 0);
    foreach ($checks as $group) {
        foreach ($group['items'] as $item) {
            $s = isset($item['status']) ? $item['status'] : 'error';
            if (isset($summary[$s])) {
                $summary[$s]++;
            }
        }
    }

    return array(
        'version' => legion_asset_version(),
        'checkedAt' => date('d.m.Y H:i:s'),
        'summary' => $summary,
        'checks' => $checks,
    );
}
