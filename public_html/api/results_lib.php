<?php

require_once __DIR__ . '/ranks_lib.php';
require_once __DIR__ . '/diagnostics_lib.php';
require_once __DIR__ . '/sheets_cache_lib.php';

function legion_parse_results_csv($text) {
    $text = preg_replace('/^\xEF\xBB\xBF/', '', (string) $text);
    $lines = preg_split('/\r\n|\r|\n/', trim($text));
    $lines = array_values(array_filter($lines, function ($line) {
        return trim($line) !== '';
    }));
    if (count($lines) < 2) {
        return array();
    }

    $headers = array_map('trim', explode(',', $lines[0]));
    $lower = array_map(function ($h) {
        return function_exists('mb_strtolower') ? mb_strtolower($h, 'UTF-8') : strtolower($h);
    }, $headers);

    $nameIdx = -1;
    $photoIdx = -1;
    foreach ($lower as $i => $h) {
        if ($nameIdx === -1 && (strpos($h, 'фио') !== false || strpos($h, 'имя') !== false)) {
            $nameIdx = $i;
        }
        if ($photoIdx === -1 && strpos($h, 'фото') !== false) {
            $photoIdx = $i;
        }
    }
    if ($nameIdx === -1) {
        return array();
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
        if ($idx === -1) {
            return array();
        }
        $colIdx[$ex['key']] = $idx;
    }

    $minCols = max(array_merge(array($nameIdx, $photoIdx >= 0 ? $photoIdx : $nameIdx), array_values($colIdx))) + 1;
    $rows = array();

    for ($i = 1; $i < count($lines); $i++) {
        $cols = array_map('trim', explode(',', $lines[$i]));
        if (count($cols) < $minCols) {
            continue;
        }
        $name = legion_normalize_person_name($cols[$nameIdx]);
        if ($name === '') {
            continue;
        }

        $row = array(
            'name' => $name,
            'photo' => ($photoIdx >= 0 && isset($cols[$photoIdx])) ? $cols[$photoIdx] : '',
        );
        $valid = true;
        foreach ($exercises as $ex) {
            $val = isset($cols[$colIdx[$ex['key']]]) ? $cols[$colIdx[$ex['key']]] : '';
            if ($val === '' || !is_numeric($val)) {
                $valid = false;
                break;
            }
            $row[$ex['key']] = (float) $val;
        }
        if ($valid) {
            $rows[] = $row;
        }
    }

    return $rows;
}

/**
 * @return array{athletes:array,warnings:array}
 */
function legion_load_all_athletes($coachSlugFilter = null) {
    $coaches = legion_coaches_config();
    $urls = array();
    $urlMeta = array();

    foreach ($coaches as $slug => $coach) {
        if ($coachSlugFilter !== null && $slug !== $coachSlugFilter) {
            continue;
        }
        if (empty($coach['csvUrl'])) {
            continue;
        }
        $urls[] = $coach['csvUrl'];
        $urlMeta[$coach['csvUrl']] = array(
            'slug' => $slug,
            'name' => $coach['name'],
        );
    }

    $fetched = legion_fetch_sheets_parallel($urls);
    $athletes = array();
    $warnings = array();

    foreach ($urlMeta as $url => $meta) {
        if (!isset($fetched[$url])) {
            $warnings[] = array(
                'coach' => $meta['name'],
                'slug' => $meta['slug'],
                'message' => 'Не удалось загрузить таблицу',
            );
            continue;
        }
        $result = $fetched[$url];
        if (!$result['ok']) {
            $warnings[] = array(
                'coach' => $meta['name'],
                'slug' => $meta['slug'],
                'message' => isset($result['error']) ? $result['error'] : 'Ошибка загрузки',
            );
            continue;
        }

        $parsed = legion_parse_results_csv($result['body']);
        if (count($parsed) === 0) {
            $warnings[] = array(
                'coach' => $meta['name'],
                'slug' => $meta['slug'],
                'message' => 'Таблица пуста или нет строк с результатами',
            );
            continue;
        }

        foreach ($parsed as $row) {
            $row['coach'] = $meta['name'];
            $row['coachSlug'] = $meta['slug'];
            $athletes[] = $row;
        }
    }

    return array(
        'athletes' => $athletes,
        'warnings' => $warnings,
    );
}
