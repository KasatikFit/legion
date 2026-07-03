<?php

require_once __DIR__ . '/coaches.php';
require_once __DIR__ . '/diagnostics_lib.php';
require_once __DIR__ . '/sheets_cache_lib.php';

function legion_normalize_person_name($name) {
    $name = str_replace("\xC2\xA0", ' ', (string) $name);
    $name = trim($name);
    $name = preg_replace('/\s+/u', ' ', $name);
    return $name;
}

function legion_is_rank_mark($value) {
    $v = trim((string) $value);
    $v = trim($v, "\"'");
    if ($v === '') {
        return false;
    }
    $lower = function_exists('mb_strtolower') ? mb_strtolower($v, 'UTF-8') : strtolower($v);
    if (in_array($lower, array('1', 'x', 'х', '✓', '+', 'да', 'yes', 'true'), true)) {
        return true;
    }
    if (in_array($lower, array('0', 'false', 'нет', 'no'), true)) {
        return false;
    }
    if (is_numeric($v) && (float) $v > 0) {
        return true;
    }
    return false;
}

function legion_parse_rank_marks_from_row($cols, $headers, $nameIdx) {
    $marks = array();
    for ($j = 0; $j < count($headers); $j++) {
        if ($j === $nameIdx) {
            continue;
        }
        $marks[] = legion_is_rank_mark(isset($cols[$j]) ? $cols[$j] : '') ? 1 : 0;
    }
    while (count($marks) < 60) {
        $marks[] = 0;
    }
    return array_slice($marks, 0, 60);
}

function legion_parse_rank_csv_entries($text) {
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
    foreach ($lower as $i => $h) {
        if (strpos($h, 'фио') !== false || strpos($h, 'имя') !== false) {
            $nameIdx = $i;
            break;
        }
    }
    if ($nameIdx === -1) {
        return array();
    }

    $entries = array();
    for ($i = 1; $i < count($lines); $i++) {
        $cols = array_map('trim', explode(',', $lines[$i]));
        $marks = legion_parse_rank_marks_from_row($cols, $headers, $nameIdx);
        $hasMarks = false;
        foreach ($marks as $m) {
            if ($m) {
                $hasMarks = true;
                break;
            }
        }
        if (!$hasMarks) {
            continue;
        }
        $entries[] = array(
            'name' => legion_normalize_person_name(isset($cols[$nameIdx]) ? $cols[$nameIdx] : ''),
            'marks' => $marks,
        );
    }
    return $entries;
}

function legion_get_coach_result_names_from_body($body) {
    $text = preg_replace('/^\xEF\xBB\xBF/', '', (string) $body);
    $lines = preg_split('/\r\n|\r|\n/', trim($text));
    if (count($lines) < 2) {
        return array();
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
        return array();
    }

    $names = array();
    for ($i = 1; $i < count($lines); $i++) {
        $cols = array_map('trim', explode(',', $lines[$i]));
        if (!isset($cols[$nameIdx])) {
            continue;
        }
        $name = legion_normalize_person_name($cols[$nameIdx]);
        if ($name !== '') {
            $names[] = $name;
        }
    }
    return $names;
}

function legion_get_coach_result_names($coach, $resultsBodies = null) {
    if (empty($coach['csvUrl'])) {
        return array();
    }
    if (is_array($resultsBodies) && isset($resultsBodies[$coach['csvUrl']]) && $resultsBodies[$coach['csvUrl']]['ok']) {
        return legion_get_coach_result_names_from_body($resultsBodies[$coach['csvUrl']]['body']);
    }
    $fetch = legion_fetch_sheet_cached($coach['csvUrl']);
    if (!$fetch['ok']) {
        return array();
    }
    return legion_get_coach_result_names_from_body($fetch['body']);
}

function legion_merge_coach_rank_entries($entries, $coachNames) {
    $merged = array();
    $fallbackIdx = 0;

    foreach ($entries as $entry) {
        $name = $entry['name'];
        if ($name === '') {
            while ($fallbackIdx < count($coachNames) && isset($merged[$coachNames[$fallbackIdx]])) {
                $fallbackIdx++;
            }
            if ($fallbackIdx < count($coachNames)) {
                $name = $coachNames[$fallbackIdx];
                $fallbackIdx++;
            }
        }
        if ($name === '') {
            continue;
        }
        $key = legion_normalize_person_name($name);
        $merged[$key] = $entry['marks'];
    }

    return $merged;
}

function legion_count_rank_marks($marks) {
    $count = 0;
    foreach ($marks as $m) {
        if ($m) {
            $count++;
        }
    }
    return $count;
}

function legion_load_all_ranks() {
    $coaches = legion_coaches_config();
    $merged = array();
    $coachesStats = array();

    $rankUrls = array();
    $resultUrls = array();
    foreach ($coaches as $slug => $coach) {
        if (!empty($coach['ranksCsvUrl'])) {
            $rankUrls[] = $coach['ranksCsvUrl'];
        }
        if (!empty($coach['csvUrl'])) {
            $resultUrls[] = $coach['csvUrl'];
        }
    }

    $rankUrls = array_values(array_unique($rankUrls));
    $resultUrls = array_values(array_unique($resultUrls));

    $fetched = array();
    if (!empty($resultUrls)) {
        $fetched = array_merge($fetched, legion_fetch_sheets_parallel($resultUrls, null, true));
    }
    if (!empty($rankUrls)) {
        $fetched = array_merge($fetched, legion_fetch_sheets_parallel($rankUrls, null, false));
    }

    foreach ($coaches as $slug => $coach) {
        $stat = array(
            'slug' => $slug,
            'name' => $coach['name'],
            'ok' => false,
            'athletes' => 0,
            'withMarks' => 0,
            'error' => '',
        );

        if (empty($coach['ranksCsvUrl'])) {
            $stat['error'] = 'Не задан ranksCsvUrl';
            $coachesStats[] = $stat;
            continue;
        }

        if (!isset($fetched[$coach['ranksCsvUrl']]) || !$fetched[$coach['ranksCsvUrl']]['ok']) {
            $stat['error'] = isset($fetched[$coach['ranksCsvUrl']]['error'])
                ? $fetched[$coach['ranksCsvUrl']]['error']
                : 'Не удалось загрузить таблицу рангов';
            $coachesStats[] = $stat;
            continue;
        }

        $entries = legion_parse_rank_csv_entries($fetched[$coach['ranksCsvUrl']]['body']);
        $coachNames = legion_get_coach_result_names($coach, $fetched);
        $coachRanks = legion_merge_coach_rank_entries($entries, $coachNames);
        $withMarks = 0;

        foreach ($coachRanks as $name => $marks) {
            $stat['athletes']++;
            if (legion_count_rank_marks($marks) > 0) {
                $withMarks++;
            }
            $key = legion_normalize_person_name($name);
            if ($key === '') {
                continue;
            }
            $scopedKey = $slug . ':' . $key;
            $merged[$scopedKey] = $marks;
            if (!isset($merged[$key]) || legion_count_rank_marks($marks) > legion_count_rank_marks($merged[$key])) {
                $merged[$key] = $marks;
            }
        }

        $stat['withMarks'] = $withMarks;
        $stat['ok'] = $withMarks > 0;
        if (!$stat['ok']) {
            if (count($entries) > 0) {
                $stat['error'] = 'Отметки есть, но ФИО не сопоставились с результатами';
            } else {
                $stat['error'] = 'Нет строк с отметками';
            }
        }
        $coachesStats[] = $stat;
    }

    return array(
        'ranks' => $merged,
        'loaded' => count($merged),
        'coaches' => $coachesStats,
    );
}
