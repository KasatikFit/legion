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
        'api/get_elite.php',
        'api/save_elite.php',
        'api/verify_rotation_password.php',
        'api/diagnostics_lib.php',
        'api/ranks_lib.php',
        'api/get_ranks.php',
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

    $rotationPath = $root . '/api/rotation_config.php';
    $checks[] = array(
        'group' => 'Ротация',
        'items' => array(
            array(
                'name' => 'api/rotation_config.php',
                'status' => is_file($rotationPath) ? 'ok' : 'warn',
                'detail' => is_file($rotationPath) ? 'настроен' : 'не найден — ручная ротация не работает',
            ),
        ),
    );

    $apiDir = $root . '/api';
    $jsonFiles = array('elite.json', 'history.json', 'achievements.json', 'last_results.json');
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

    $coaches = legion_coaches_config();
    $coachItems = array();
    foreach ($coaches as $slug => $coach) {
        $pagePath = $root . '/' . $slug . '/index.php';
        if (!is_file($pagePath)) {
            $coachItems[] = array(
                'name' => $coach['name'] . ' — страница /' . $slug . '/',
                'status' => 'error',
                'detail' => 'нет ' . $slug . '/index.php',
            );
            continue;
        }

        $table = legion_diagnostics_check_coach_table($slug, $coach);
        $coachItems[] = array(
            'name' => $coach['name'] . ' — результаты',
            'status' => $table['status'],
            'detail' => $table['message'],
        );

        $ranks = legion_diagnostics_check_coach_ranks($slug, $coach);
        $coachItems[] = array(
            'name' => $coach['name'] . ' — ранги',
            'status' => $ranks['status'],
            'detail' => $ranks['message'],
        );
    }
    $checks[] = array('group' => 'Тренеры, результаты и ранги', 'items' => $coachItems);

    require_once __DIR__ . '/ranks_lib.php';
    $allRanks = legion_load_all_ranks();
    $checks[] = array(
        'group' => 'API рангов',
        'items' => array(
            array(
                'name' => 'api/get_ranks.php',
                'status' => $allRanks['loaded'] > 0 ? 'ok' : 'error',
                'detail' => $allRanks['loaded'] > 0
                    ? 'Загружено ' . $allRanks['loaded'] . ' спортсменов с рангами'
                    : 'Ни одного ранга не сопоставлено — проверьте ФИО в таблицах',
            ),
        ),
    );

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
