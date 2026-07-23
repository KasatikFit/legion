<?php

require_once __DIR__ . '/ranks_lib.php';
require_once __DIR__ . '/diagnostics_lib.php';
require_once __DIR__ . '/storage_lib.php';

define('LEGION_HISTORY_PER_ATHLETE', 50);

function legion_history_now_ru() {
    return date('d.m.Y, H:i:s');
}

function legion_history_exercise_keys() {
    return array_column(legion_diagnostics_exercises(), 'key');
}

function legion_normalize_last_results_map(array $lastResults) {
    $out = array();
    foreach ($lastResults as $key => $marks) {
        if (!is_array($marks)) {
            continue;
        }
        $out[legion_normalize_person_name($key)] = $marks;
    }
    return $out;
}

function legion_snapshot_current_results(array $athletes) {
    $out = array();
    $exercises = legion_history_exercise_keys();
    foreach ($athletes as $athlete) {
        if (!is_array($athlete)) {
            continue;
        }
        $name = legion_normalize_person_name(isset($athlete['name']) ? $athlete['name'] : '');
        if ($name === '') {
            continue;
        }
        $row = array();
        foreach ($exercises as $ex) {
            $row[$ex] = isset($athlete[$ex]) ? $athlete[$ex] : 0;
        }
        $out[$name] = $row;
    }
    return $out;
}

function legion_build_history_entries(array $lastResults, array $athletes) {
    $now = legion_history_now_ru();
    $entries = array();
    $exercises = legion_history_exercise_keys();
    $normalizedLast = legion_normalize_last_results_map($lastResults);

    foreach ($athletes as $athlete) {
        if (!is_array($athlete)) {
            continue;
        }
        $name = legion_normalize_person_name(isset($athlete['name']) ? $athlete['name'] : '');
        if ($name === '' || !isset($normalizedLast[$name])) {
            continue;
        }
        foreach ($exercises as $ex) {
            $oldVal = isset($normalizedLast[$name][$ex]) ? $normalizedLast[$name][$ex] : null;
            $newVal = isset($athlete[$ex]) ? $athlete[$ex] : null;
            $oldNum = is_numeric($oldVal) ? (float) $oldVal : null;
            $newNum = is_numeric($newVal) ? (float) $newVal : null;
            if ($oldNum !== null && $newNum !== null && $oldNum === $newNum) {
                continue;
            }
            if ($oldNum === null && $newNum === null) {
                continue;
            }
            $entries[] = array(
                'date' => $now,
                'name' => $name,
                'exercise' => $ex,
                'oldVal' => $oldNum !== null ? $oldNum : $oldVal,
                'newVal' => $newNum !== null ? $newNum : $newVal,
                'diff' => ($newNum !== null ? $newNum : 0) - ($oldNum !== null ? $oldNum : 0),
            );
        }
    }

    return $entries;
}

function legion_trim_history_per_athlete(array $entries, $limit = LEGION_HISTORY_PER_ATHLETE) {
    $indicesByName = array();
    foreach ($entries as $i => $entry) {
        $name = isset($entry['name']) ? $entry['name'] : '';
        if (!isset($indicesByName[$name])) {
            $indicesByName[$name] = array();
        }
        $indicesByName[$name][] = $i;
    }

    $keep = array();
    foreach ($indicesByName as $indices) {
        foreach (array_slice($indices, -$limit) as $i) {
            $keep[$i] = true;
        }
    }

    $trimmed = array();
    foreach ($entries as $i => $entry) {
        if (isset($keep[$i])) {
            $trimmed[] = $entry;
        }
    }
    return $trimmed;
}

function legion_append_history_entries(array $entries) {
    if (count($entries) === 0) {
        return 0;
    }
    if (function_exists('legion_club_storage_enabled') && legion_club_storage_enabled()) {
        require_once __DIR__ . '/club_storage_lib.php';
        // История результатов пишется в pilot_history при сохранении в режиме тренировки.
        return count($entries);
    }
    $historyFile = __DIR__ . '/history.json';
    $existing = storage_read_json($historyFile, array());
    $existing = array_merge($existing, $entries);
    $existing = legion_trim_history_per_athlete($existing);
    if (!storage_write_json($historyFile, $existing)) {
        throw new RuntimeException('Не удалось сохранить history.json');
    }
    return count($entries);
}

function legion_save_last_results_snapshot($scope, array $snapshot) {
    $scope = storage_validate_scope($scope);
    if ($scope === null) {
        throw new InvalidArgumentException('Неверный scope');
    }
    require_once __DIR__ . '/club_storage_lib.php';
    legion_club_save_snapshot($scope, 'results', $snapshot);
}

function legion_load_last_results_baseline($scope = 'global') {
    require_once __DIR__ . '/club_storage_lib.php';
    $baseline = legion_club_load_snapshot($scope, 'results');
    if (count($baseline) === 0 && $scope === 'global' && !legion_club_storage_enabled()) {
        $file = __DIR__ . '/last_results.json';
        $all = storage_read_json($file, array());
        $baseline = storage_merge_last_results($all);
    }
    return $baseline;
}

function legion_save_snapshot_meta(array $meta) {
    require_once __DIR__ . '/club_storage_lib.php';
    legion_club_save_snapshot_meta($meta);
}

function legion_load_snapshot_meta() {
    require_once __DIR__ . '/club_storage_lib.php';
    return legion_club_load_snapshot_meta();
}

function legion_lookup_rank_marks_map($name, $coachSlug, array $merged) {
    $norm = legion_normalize_person_name($name);
    if ($norm === '') {
        return null;
    }
    if ($coachSlug !== '') {
        $scoped = $coachSlug . ':' . $norm;
        if (isset($merged[$scoped]) && is_array($merged[$scoped])) {
            return $merged[$scoped];
        }
    }
    if (isset($merged[$norm]) && is_array($merged[$norm])) {
        return $merged[$norm];
    }
    return null;
}

function legion_rank_counts_from_marks($marks) {
    if (!is_array($marks)) {
        return null;
    }
    $bronze = 0;
    $silver = 0;
    $gold = 0;
    for ($i = 0; $i < 20; $i++) {
        if (!empty($marks[$i])) {
            $bronze++;
        }
    }
    for ($i = 20; $i < 40; $i++) {
        if (!empty($marks[$i])) {
            $silver++;
        }
    }
    for ($i = 40; $i < 60; $i++) {
        if (!empty($marks[$i])) {
            $gold++;
        }
    }
    return array(
        'bronze' => $bronze,
        'silver' => $silver,
        'gold' => $gold,
    );
}

function legion_snapshot_current_ranks(array $athletes, array $mergedRanks) {
    $out = array();
    foreach ($athletes as $athlete) {
        if (!is_array($athlete)) {
            continue;
        }
        $name = legion_normalize_person_name(isset($athlete['name']) ? $athlete['name'] : '');
        if ($name === '') {
            continue;
        }
        $slug = isset($athlete['coachSlug']) ? (string) $athlete['coachSlug'] : '';
        $marks = legion_lookup_rank_marks_map($name, $slug, $mergedRanks);
        $counts = legion_rank_counts_from_marks($marks);
        if ($counts === null) {
            continue;
        }
        $key = ($slug !== '') ? ($slug . ':' . $name) : $name;
        $counts['name'] = $name;
        if ($slug !== '') {
            $counts['coachSlug'] = $slug;
        }
        if (!empty($athlete['id'])) {
            $counts['athleteId'] = (int) $athlete['id'];
        }
        $out[$key] = $counts;
    }
    return $out;
}

function legion_build_rank_history_entries(array $baseline, array $current, $date) {
    $entries = array();
    foreach ($current as $key => $newCounts) {
        if (!is_array($newCounts)) {
            continue;
        }
        $oldCounts = null;
        if (isset($baseline[$key]) && is_array($baseline[$key])) {
            $oldCounts = $baseline[$key];
        } elseif (isset($newCounts['name'])) {
            // Dual-read: старый baseline по голому ФИО
            $bare = legion_normalize_person_name($newCounts['name']);
            if ($bare !== '' && isset($baseline[$bare]) && is_array($baseline[$bare])) {
                $oldCounts = $baseline[$bare];
            }
        }
        if ($oldCounts === null) {
            continue;
        }
        $name = isset($newCounts['name'])
            ? legion_normalize_person_name($newCounts['name'])
            : (strpos((string) $key, ':') !== false
                ? legion_normalize_person_name(substr((string) $key, strpos((string) $key, ':') + 1))
                : legion_normalize_person_name($key));
        if ($name === '') {
            continue;
        }
        $base = array(
            'date' => $date,
            'name' => $name,
        );
        if (!empty($newCounts['coachSlug'])) {
            $base['coachSlug'] = (string) $newCounts['coachSlug'];
        } elseif (strpos((string) $key, ':') !== false) {
            $base['coachSlug'] = substr((string) $key, 0, strpos((string) $key, ':'));
        }
        if (!empty($newCounts['athleteId'])) {
            $base['athleteId'] = (int) $newCounts['athleteId'];
        }
        if ($oldCounts['bronze'] < 20 && $newCounts['bronze'] >= 20) {
            $entries[] = array_merge($base, array('event' => 'league_bronze'));
        }
        if ($oldCounts['bronze'] >= 20 && $oldCounts['silver'] < 20 && $newCounts['silver'] >= 20) {
            $entries[] = array_merge($base, array('event' => 'league_silver'));
        }
        if ($oldCounts['silver'] >= 20 && $oldCounts['gold'] < 20 && $newCounts['gold'] >= 20) {
            $entries[] = array_merge($base, array('event' => 'league_gold'));
        }
    }
    return $entries;
}

function legion_log_rank_mark_change($name, $markIndex, $oldVal, $newVal, $date = null, $athleteId = 0, $coachSlug = '') {
    $oldVal = (int) $oldVal;
    $newVal = (int) $newVal;
    if ($oldVal === $newVal) {
        return;
    }
    $markIndex = (int) $markIndex;
    if ($markIndex < 0 || $markIndex >= 60) {
        return;
    }
    if ($oldVal > 0 && $newVal > 0) {
        return;
    }
    if ($date === null) {
        $date = legion_history_now_ru();
    }
    $entry = array(
        'date' => $date,
        'name' => legion_normalize_person_name($name),
        'event' => $newVal > 0 ? 'mark_pass' : 'mark_revoke',
        'markIndex' => $markIndex,
        'oldVal' => $oldVal,
        'newVal' => $newVal,
    );
    if ((int) $athleteId > 0) {
        $entry['athleteId'] = (int) $athleteId;
    }
    if ($coachSlug !== '') {
        $entry['coachSlug'] = (string) $coachSlug;
    }
    legion_append_rank_history_entries(array($entry));
}

function legion_append_rank_history_entries(array $entries) {
    if (count($entries) === 0) {
        return 0;
    }
    if (function_exists('legion_pilot_db_enabled') && legion_pilot_db_enabled()) {
        require_once __DIR__ . '/pilot_db_lib.php';
        return legion_pilot_db_append_rank_history_entries($entries);
    }
    $file = __DIR__ . '/rank_history.json';
    $existing = storage_read_json($file, array());
    $existing = array_merge($existing, $entries);
    $existing = legion_trim_history_per_athlete($existing);
    if (!storage_write_json($file, $existing)) {
        throw new RuntimeException('Не удалось сохранить rank_history.json');
    }
    return count($entries);
}

function legion_save_last_ranks_snapshot($scope, array $snapshot) {
    $scope = storage_validate_scope($scope);
    if ($scope === null) {
        throw new InvalidArgumentException('Неверный scope');
    }
    require_once __DIR__ . '/club_storage_lib.php';
    legion_club_save_snapshot($scope, 'ranks', $snapshot);
}

function legion_load_last_ranks_baseline($scope = 'global') {
    require_once __DIR__ . '/club_storage_lib.php';
    return legion_club_load_snapshot($scope, 'ranks');
}

function legion_process_rank_snapshot(array $athletes, $scope = 'global') {
    $ranksResult = legion_load_all_ranks();
    $merged = isset($ranksResult['ranks']) && is_array($ranksResult['ranks']) ? $ranksResult['ranks'] : array();
    $current = legion_snapshot_current_ranks($athletes, $merged);
    if (count($current) === 0) {
        return 0;
    }

    $baseline = legion_load_last_ranks_baseline($scope);
    $recorded = 0;
    $date = legion_history_now_ru();

    if (count($baseline) === 0) {
        legion_save_last_ranks_snapshot($scope, $current);
        return 0;
    }

    $entries = legion_build_rank_history_entries($baseline, $current, $date);
    if (count($entries) > 0) {
        $recorded = legion_append_rank_history_entries($entries);
    }
    legion_save_last_ranks_snapshot($scope, $current);
    return $recorded;
}
