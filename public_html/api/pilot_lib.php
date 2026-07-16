<?php

require_once __DIR__ . '/diagnostics_lib.php';
require_once __DIR__ . '/ranks_lib.php';
require_once __DIR__ . '/storage_lib.php';
require_once __DIR__ . '/coach_data_lib.php';
require_once __DIR__ . '/coach_auth_lib.php';

define('LEGION_PILOT_SLUG', 'pilot-demo');
define('LEGION_PILOT_HISTORY_PER_ATHLETE', 50);

class LegionPilotNeedsPatronymicException extends InvalidArgumentException {
    public $baseName;

    public function __construct($baseName) {
        $this->baseName = legion_normalize_person_name($baseName);
        parent::__construct('Укажите первую букву отчества');
    }
}

function legion_pilot_now_ru() {
    return date('d.m.Y, H:i:s');
}

function legion_pilot_ensure_meta_arrays(array &$data) {
    if (!isset($data['history']) || !is_array($data['history'])) {
        $data['history'] = array();
    }
    if (!isset($data['achievements']) || !is_array($data['achievements'])) {
        $data['achievements'] = array();
    }
}

function legion_pilot_new_history_id() {
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes(8));
    }
    return uniqid('h', true);
}

function legion_pilot_athlete_score(array $row) {
    $sum = 0.0;
    foreach (legion_pilot_exercise_keys() as $key) {
        if (isset($row[$key]) && is_numeric($row[$key])) {
            $sum += (float) $row[$key];
        }
    }
    return $sum;
}

function legion_pilot_count_league_marks(array $marks, $league) {
    $offset = $league === 3 ? 0 : ($league === 2 ? 20 : 40);
    $count = 0;
    for ($i = 0; $i < 20; $i++) {
        if (!empty($marks[$offset + $i])) {
            $count++;
        }
    }
    return $count;
}

/**
 * Откат лиги: если сняты все галочки серебра — открывается бронза снова;
 * если сняты все галочки золота при пройденном серебре — откатывается серебро.
 */
function legion_pilot_apply_league_rollback(array &$marks, $changedIndex, $newValue) {
    if ($newValue !== 0) {
        return;
    }
    $changedIndex = (int) $changedIndex;
    $count3 = legion_pilot_count_league_marks($marks, 3);
    $count2 = legion_pilot_count_league_marks($marks, 2);
    $count1 = legion_pilot_count_league_marks($marks, 1);

    if ($changedIndex >= 20 && $changedIndex < 40 && $count2 === 0 && $count3 >= 20) {
        $marks[19] = 0;
        return;
    }

    if ($changedIndex >= 40 && $changedIndex < 60 && $count1 === 0 && $count2 >= 20) {
        $marks[39] = 0;
    }
}

function legion_pilot_grant_achievement(array &$stored, $name, $id, $date) {
    $name = legion_normalize_person_name($name);
    if ($name === '') {
        return;
    }
    if (!isset($stored[$name]) || !is_array($stored[$name])) {
        $stored[$name] = array();
    }
    foreach ($stored[$name] as $item) {
        if (is_array($item) && isset($item['id']) && $item['id'] === $id) {
            return;
        }
    }
    $stored[$name][] = array(
        'id' => $id,
        'date' => $date,
    );
}

function legion_pilot_trim_history(array $history) {
    $indicesByName = array();
    foreach ($history as $i => $entry) {
        $name = is_array($entry) && isset($entry['name']) ? $entry['name'] : '';
        if (!isset($indicesByName[$name])) {
            $indicesByName[$name] = array();
        }
        $indicesByName[$name][] = $i;
    }

    $keep = array();
    foreach ($indicesByName as $indices) {
        $slice = array_slice($indices, -LEGION_PILOT_HISTORY_PER_ATHLETE);
        foreach ($slice as $i) {
            $keep[$i] = true;
        }
    }

    $trimmed = array();
    foreach ($history as $i => $entry) {
        if (isset($keep[$i])) {
            $trimmed[] = $entry;
        }
    }
    return $trimmed;
}

function legion_pilot_recompute_achievements(array $data) {
    $stored = isset($data['achievements']) && is_array($data['achievements'])
        ? $data['achievements']
        : array();
    $today = date('Y-m-d');
    $athletes = isset($data['athletes']) && is_array($data['athletes']) ? $data['athletes'] : array();
    $history = isset($data['history']) && is_array($data['history']) ? $data['history'] : array();
    $exercises = legion_pilot_exercise_keys();

    $scored = array();
    foreach ($athletes as $row) {
        if (!is_array($row) || empty($row['name'])) {
            continue;
        }
        $scored[] = array(
            'name' => $row['name'],
            'score' => legion_pilot_athlete_score($row),
            'row' => $row,
        );
    }
    usort($scored, function ($a, $b) {
        if ($a['score'] == $b['score']) {
            return 0;
        }
        return ($a['score'] < $b['score']) ? 1 : -1;
    });

    foreach ($scored as $idx => $item) {
        $name = $item['name'];
        $place = $idx + 1;
        if ($place === 1 && $item['score'] > 0) {
            legion_pilot_grant_achievement($stored, $name, 'pilot_top1', $today);
        }
        if ($place <= 3 && $item['score'] > 0) {
            legion_pilot_grant_achievement($stored, $name, 'pilot_top3', $today);
        }
    }

    foreach ($exercises as $ex) {
        $best = null;
        foreach ($athletes as $row) {
            if (!is_array($row) || empty($row['name'])) {
                continue;
            }
            $val = isset($row[$ex]) ? (float) $row[$ex] : 0;
            if ($val <= 0) {
                continue;
            }
            if ($best === null || $val > $best['val']) {
                $best = array('name' => $row['name'], 'val' => $val);
            }
        }
        if ($best !== null) {
            legion_pilot_grant_achievement($stored, $best['name'], 'pilot_best_' . $ex, $today);
        }
    }

    foreach ($athletes as $row) {
        if (!is_array($row) || empty($row['name'])) {
            continue;
        }
        $name = $row['name'];
        $marks = legion_pilot_athlete_marks($row);
        if (legion_pilot_count_league_marks($marks, 3) >= 20) {
            legion_pilot_grant_achievement($stored, $name, 'rank_bronze_done', $today);
        }
        if (legion_pilot_count_league_marks($marks, 2) >= 20) {
            legion_pilot_grant_achievement($stored, $name, 'rank_silver_done', $today);
        }
        if (legion_pilot_count_league_marks($marks, 1) >= 20) {
            legion_pilot_grant_achievement($stored, $name, 'rank_gold_done', $today);
        }
    }

    foreach ($history as $entry) {
        if (!is_array($entry) || empty($entry['name'])) {
            continue;
        }
        $diff = isset($entry['diff']) ? (float) $entry['diff'] : 0;
        if ($diff > 0) {
            legion_pilot_grant_achievement($stored, $entry['name'], 'pilot_first_gain', $today);
        }
    }

    $data['achievements'] = $stored;
    return $data;
}

function legion_pilot_append_history_entry($name, $exercise, $oldVal, $newVal) {
    $data = legion_pilot_load_dataset();
    legion_pilot_ensure_meta_arrays($data);

    $oldNum = is_numeric($oldVal) ? (float) $oldVal : null;
    $newNum = is_numeric($newVal) ? (float) $newVal : null;
    if ($oldNum !== null && $newNum !== null && $newNum <= $oldNum) {
        return $data;
    }

    $data['history'][] = array(
        'id' => legion_pilot_new_history_id(),
        'date' => legion_pilot_now_ru(),
        'name' => legion_normalize_person_name($name),
        'exercise' => $exercise,
        'oldVal' => $oldNum !== null ? $oldNum : $oldVal,
        'newVal' => $newNum !== null ? $newNum : $newVal,
        'diff' => ($newNum !== null ? $newNum : 0) - ($oldNum !== null ? $oldNum : 0),
    );
    $data['history'] = legion_pilot_trim_history($data['history']);
    $data = legion_pilot_recompute_achievements($data);
    return legion_pilot_save_dataset($data);
}

function legion_pilot_delete_history_entry($entryId, $coachSlug = LEGION_PILOT_SLUG) {
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    $entryId = trim((string) $entryId);
    if ($entryId === '') {
        throw new InvalidArgumentException('Не указана запись');
    }

    $data = legion_pilot_load_dataset($coachSlug);
    legion_pilot_ensure_meta_arrays($data);

    $found = false;
    $next = array();
    foreach ($data['history'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if (isset($entry['id']) && (string) $entry['id'] === $entryId) {
            $found = true;
            continue;
        }
        $next[] = $entry;
    }
    if (!$found) {
        throw new InvalidArgumentException('Запись не найдена');
    }

    $data['history'] = $next;
    $data = legion_pilot_recompute_achievements($data);
    return legion_pilot_save_dataset($data);
}

function legion_pilot_coach_meta($coachSlug = LEGION_PILOT_SLUG) {
    return legion_coach_meta($coachSlug);
}

function legion_pilot_data_path($coachSlug = LEGION_PILOT_SLUG) {
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    if ($coachSlug === LEGION_PILOT_SLUG) {
        return __DIR__ . '/data/pilot-demo.json';
    }
    return legion_coach_data_json_path($coachSlug);
}

function legion_pilot_default_avatar_count() {
    return 10;
}

function legion_pilot_default_avatar_index($name) {
    $norm = legion_normalize_person_name($name);
    if ($norm === '') {
        return 1;
    }
    $hash = crc32($norm);
    if ($hash < 0) {
        $hash = -$hash;
    }
    return ($hash % legion_pilot_default_avatar_count()) + 1;
}

function legion_pilot_default_avatar_url($name) {
    $index = legion_pilot_default_avatar_index($name);
    return '/api/pilot/default_avatar.php?i=' . $index;
}

function legion_pilot_default_avatar_data_uri($name) {
    if (!function_exists('legion_pilot_avatar_svg_content')) {
        require_once __DIR__ . '/pilot_avatars_lib.php';
    }
    $index = legion_pilot_default_avatar_index($name);
    $svg = legion_pilot_avatar_svg_content($index);
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function legion_pilot_athlete_has_uploaded_photo($photo, $coachSlug = LEGION_PILOT_SLUG) {
    return legion_coach_athlete_has_uploaded_photo($coachSlug, $photo);
}

function legion_pilot_resolve_photo_url($name, $photo = '', $coachSlug = LEGION_PILOT_SLUG) {
    $photo = trim((string) $photo);
    if ($photo !== '') {
        if (preg_match('#^https?://#i', $photo)) {
            return $photo;
        }
        if ($photo[0] === '/') {
            return $photo;
        }
        return '/' . ltrim($photo, '/');
    }
    return legion_pilot_default_avatar_url($name);
}

function legion_pilot_photos_dir($coachSlug = LEGION_PILOT_SLUG) {
    return legion_coach_photos_dir($coachSlug);
}

function legion_pilot_photo_storage_basename($name, $coachSlug = LEGION_PILOT_SLUG) {
    return legion_coach_photo_storage_basename($coachSlug, $name);
}

function legion_pilot_delete_uploaded_photo_files($basename, $coachSlug = LEGION_PILOT_SLUG) {
    $dir = legion_pilot_photos_dir($coachSlug);
    if (!is_dir($dir)) {
        return;
    }
    $pattern = $dir . '/' . $basename . '.*';
    foreach (glob($pattern) as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function legion_pilot_update_athlete_photo($name, $photoPath, $coachSlug = LEGION_PILOT_SLUG) {
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    $name = legion_normalize_person_name($name);
    if ($name === '') {
        throw new InvalidArgumentException('Укажите ФИО');
    }

    $data = legion_pilot_load_dataset($coachSlug);
    $idx = legion_pilot_find_athlete_index($data['athletes'], $name);
    if ($idx < 0) {
        throw new InvalidArgumentException('Спортсмен не найден');
    }

    $data['athletes'][$idx]['photo'] = (string) $photoPath;
    $saved = legion_pilot_save_dataset($data);
    require_once __DIR__ . '/page_data_lib.php';
    legion_page_data_cache_clear($coachSlug);
    return $saved;
}

function legion_pilot_remove_athlete_photo($name, $coachSlug = LEGION_PILOT_SLUG) {
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    $name = legion_normalize_person_name($name);
    if ($name === '') {
        throw new InvalidArgumentException('Укажите ФИО');
    }

    $data = legion_pilot_load_dataset($coachSlug);
    $idx = legion_pilot_find_athlete_index($data['athletes'], $name);
    if ($idx < 0) {
        throw new InvalidArgumentException('Спортсмен не найден');
    }

    $stored = isset($data['athletes'][$idx]['photo']) ? (string) $data['athletes'][$idx]['photo'] : '';
    if (legion_pilot_athlete_has_uploaded_photo($stored, $coachSlug)) {
        legion_pilot_delete_uploaded_photo_files(legion_pilot_photo_storage_basename($name, $coachSlug), $coachSlug);
    }

    $data['athletes'][$idx]['photo'] = '';
    $saved = legion_pilot_save_dataset($data);
    require_once __DIR__ . '/page_data_lib.php';
    legion_page_data_cache_clear($coachSlug);
    return $saved;
}

function legion_pilot_upload_athlete_photo($name, array $file, $coachSlug = LEGION_PILOT_SLUG) {
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    $name = legion_normalize_person_name($name);
    if ($name === '') {
        throw new InvalidArgumentException('Укажите ФИО');
    }

    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('Файл не получен');
    }
    if (!empty($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Ошибка загрузки файла');
    }
    if (!empty($file['size']) && (int) $file['size'] > 3 * 1024 * 1024) {
        throw new InvalidArgumentException('Файл больше 3 МБ');
    }

    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    $allowed = array(
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    );
    if (!isset($allowed[$mime])) {
        throw new InvalidArgumentException('Допустимы JPG, PNG или WebP');
    }

    $dir = legion_pilot_photos_dir($coachSlug);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        throw new RuntimeException('Не удалось создать папку для фото');
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('Папка для фото недоступна для записи');
    }

    $basename = legion_pilot_photo_storage_basename($name, $coachSlug);
    legion_pilot_delete_uploaded_photo_files($basename, $coachSlug);

    $ext = $allowed[$mime];
    $filename = $basename . '.' . $ext;
    $target = $dir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Не удалось сохранить фото на сервере');
    }

    @chmod($target, 0644);
    if ($coachSlug === LEGION_PILOT_SLUG) {
        $publicPath = '/images/pilot-athletes/' . $filename;
    } else {
        $publicPath = '/images/coach-athletes/' . $coachSlug . '/' . $filename;
    }
    return legion_pilot_update_athlete_photo($name, $publicPath, $coachSlug);
}

function legion_pilot_require_auth_json($coachSlug = LEGION_PILOT_SLUG) {
    legion_coach_require_auth_json(legion_coach_normalize_slug($coachSlug));
}

function legion_pilot_is_authenticated($coachSlug = LEGION_PILOT_SLUG) {
    return legion_coach_is_authenticated(legion_coach_normalize_slug($coachSlug));
}

function legion_pilot_auth_is_configured($coachSlug = LEGION_PILOT_SLUG) {
    return legion_coach_auth_is_configured(legion_coach_normalize_slug($coachSlug));
}

function legion_pilot_verify_password($password) {
    if (!legion_pilot_auth_is_configured()) {
        return false;
    }
    require_once legion_pilot_auth_config_path();
    if (defined('PILOT_PASSWORD_HASH') && PILOT_PASSWORD_HASH !== '') {
        return password_verify((string) $password, PILOT_PASSWORD_HASH);
    }
    if (defined('PILOT_PASSWORD')) {
        return hash_equals((string) PILOT_PASSWORD, (string) $password);
    }
    return false;
}

function legion_pilot_exercise_keys() {
    return array_column(legion_diagnostics_exercises(), 'key');
}

function legion_pilot_normalize_marks($marks) {
    if (!is_array($marks)) {
        $marks = array();
    }
    $out = array();
    for ($i = 0; $i < 60; $i++) {
        $v = isset($marks[$i]) ? $marks[$i] : 0;
        $out[] = ((int) $v > 0) ? 1 : 0;
    }
    return $out;
}

function legion_pilot_default_marks($bronzeDone = 0, $silverDone = 0, $goldDone = 0) {
    $m = array_fill(0, 60, 0);
    for ($i = 0; $i < min(20, (int) $bronzeDone); $i++) {
        $m[$i] = 1;
    }
    for ($i = 0; $i < min(20, (int) $silverDone); $i++) {
        $m[20 + $i] = 1;
    }
    for ($i = 0; $i < min(20, (int) $goldDone); $i++) {
        $m[40 + $i] = 1;
    }
    return $m;
}

function legion_pilot_athlete_marks(array $row) {
    if (isset($row['rankMarks']) && is_array($row['rankMarks'])) {
        return legion_pilot_normalize_marks($row['rankMarks']);
    }
    return legion_pilot_default_marks(0, 0, 0);
}

function legion_pilot_default_dataset() {
    $exercises = legion_pilot_exercise_keys();
    $make = function ($name, $values, $bronze = 0, $silver = 0, $gold = 0) use ($exercises) {
        $row = array(
            'name' => $name,
            'photo' => '',
            'rankMarks' => legion_pilot_default_marks($bronze, $silver, $gold),
        );
        foreach ($exercises as $i => $key) {
            $row[$key] = isset($values[$i]) ? (float) $values[$i] : 0;
        }
        return $row;
    };

    return array(
        'slug' => LEGION_PILOT_SLUG,
        'coachName' => legion_pilot_coach_meta()['name'],
        'updatedAt' => date('d.m.Y, H:i:s'),
        'athletes' => array(
            $make('Алексеев Дмитрий', array(35, 8, 45, 18, 40, 210), 5, 0, 0),
            $make('Борисова Мария', array(28, 5, 38, 14, 35, 195), 8, 0, 0),
            $make('Волков Сергей', array(42, 10, 52, 20, 45, 225), 12, 3, 0),
            $make('Громова Анна', array(30, 6, 40, 16, 38, 200), 3, 0, 0),
        ),
        'history' => array(),
        'achievements' => array(),
    );
}

function legion_pilot_load_dataset($coachSlug = LEGION_PILOT_SLUG, array $options = array()) {
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    if (function_exists('legion_pilot_db_enabled') && legion_pilot_db_enabled()) {
        $data = legion_pilot_db_load_dataset($coachSlug, $options);
        legion_pilot_ensure_meta_arrays($data);
        $data['slug'] = $coachSlug;
        $recompute = !array_key_exists('recomputeAchievements', $options) || $options['recomputeAchievements'];
        if ($recompute && empty($data['achievements']) && !empty($data['athletes'])) {
            $data = legion_pilot_recompute_achievements($data);
            legion_pilot_save_dataset($data, false);
        }
        return $data;
    }

    $path = legion_pilot_data_path($coachSlug);
    $data = storage_read_json($path, null);
    if (!is_array($data) || empty($data['athletes']) || !is_array($data['athletes'])) {
        if ($coachSlug === LEGION_PILOT_SLUG) {
            $data = legion_pilot_default_dataset();
        } else {
            $meta = legion_coach_meta($coachSlug);
            $data = array(
                'slug' => $coachSlug,
                'coachName' => $meta['name'],
                'updatedAt' => date('d.m.Y, H:i:s'),
                'athletes' => array(),
                'history' => array(),
                'achievements' => array(),
            );
        }
        legion_pilot_ensure_meta_arrays($data);
        if (!empty($data['athletes'])) {
            $data = legion_pilot_recompute_achievements($data);
            legion_pilot_save_dataset($data, false);
        }
    } else {
        $data['slug'] = $coachSlug;
        $changed = false;
        foreach ($data['athletes'] as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            if (empty($row['rankMarks']) || !is_array($row['rankMarks'])) {
                $data['athletes'][$i]['rankMarks'] = legion_pilot_default_marks(0, 0, 0);
                $changed = true;
            }
        }
        legion_pilot_ensure_meta_arrays($data);
        if ($changed || (empty($data['achievements']) && !empty($data['athletes']))) {
            if (empty($data['achievements']) && !empty($data['athletes'])) {
                $data = legion_pilot_recompute_achievements($data);
            }
            legion_pilot_save_dataset($data, false);
        }
    }
    return $data;
}

function legion_pilot_save_dataset(array $data, $throwOnError = true) {
    $coachSlug = isset($data['slug']) ? legion_coach_normalize_slug($data['slug']) : LEGION_PILOT_SLUG;
    $data['slug'] = $coachSlug;
    $data['updatedAt'] = date('d.m.Y, H:i:s');

    if (function_exists('legion_pilot_db_enabled') && legion_pilot_db_enabled()) {
        return legion_pilot_db_save_dataset($data, $throwOnError);
    }

    $path = legion_pilot_data_path($coachSlug);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    if (!storage_write_json($path, $data)) {
        if ($throwOnError) {
            throw new RuntimeException('Не удалось сохранить данные группы (проверьте права на api/data/)');
        }
    }
    return $data;
}

function legion_pilot_find_athlete_index(array $athletes, $name) {
    $norm = legion_normalize_person_name($name);
    foreach ($athletes as $i => $row) {
        if (!is_array($row)) {
            continue;
        }
        if (legion_normalize_person_name(isset($row['name']) ? $row['name'] : '') === $norm) {
            return $i;
        }
    }
    return -1;
}

function legion_pilot_build_rank_map(array $athletes, $coachSlug = LEGION_PILOT_SLUG) {
    $slug = legion_coach_normalize_slug($coachSlug);
    $map = array();
    foreach ($athletes as $row) {
        if (!is_array($row) || empty($row['name'])) {
            continue;
        }
        $norm = legion_normalize_person_name($row['name']);
        $marks = legion_pilot_athlete_marks($row);
        $map[$slug . ':' . $norm] = $marks;
        $map[$norm] = $marks;
    }
    return $map;
}

function legion_pilot_dataset_for_api($coachSlug = LEGION_PILOT_SLUG, array $options = array()) {
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    $data = legion_pilot_load_dataset($coachSlug, $options);
    $meta = legion_coach_meta($coachSlug);
    $athletes = array();
    foreach ($data['athletes'] as $row) {
        if (!is_array($row) || empty($row['name'])) {
            continue;
        }
        $marks = legion_pilot_athlete_marks($row);
        $storedPhoto = isset($row['photo']) ? (string) $row['photo'] : '';
        $item = array(
            'name' => $row['name'],
            'photo' => legion_pilot_resolve_photo_url($row['name'], $storedPhoto, $coachSlug),
            'hasPhoto' => legion_pilot_athlete_has_uploaded_photo($storedPhoto, $coachSlug),
            'avatarIndex' => legion_pilot_default_avatar_index($row['name']),
            'coach' => $meta['name'],
            'coachSlug' => $meta['slug'],
            'rankMarks' => $marks,
        );
        $birthdate = isset($row['birthdate']) && $row['birthdate'] !== '' && $row['birthdate'] !== null
            ? (string) $row['birthdate']
            : null;
        $item['birthdate'] = $birthdate;
        $age = legion_pilot_compute_age($birthdate);
        if ($age !== null) {
            $item['age'] = $age;
        }
        foreach (legion_pilot_exercise_keys() as $key) {
            $item[$key] = isset($row[$key]) && is_numeric($row[$key]) ? (float) $row[$key] : 0;
        }
        $athletes[] = $item;
    }

    $coachBenchmark = legion_pilot_coach_profile_for_api($coachSlug);

    $ranks = legion_pilot_build_rank_map($data['athletes'], $coachSlug);
    if (is_array($coachBenchmark) && !empty($coachBenchmark['name'])) {
        $norm = legion_normalize_person_name($coachBenchmark['name']);
        $marks = legion_pilot_athlete_marks($coachBenchmark);
        $ranks[$coachSlug . ':' . $norm] = $marks;
        $ranks[$norm] = $marks;
    }

    return array(
        'coach' => $meta,
        'athletes' => $athletes,
        'coachBenchmark' => $coachBenchmark,
        'ranks' => $ranks,
        'history' => isset($data['history']) && is_array($data['history']) ? $data['history'] : array(),
        'achievements' => isset($data['achievements']) && is_array($data['achievements']) ? $data['achievements'] : array(),
        'updatedAt' => isset($data['updatedAt']) ? $data['updatedAt'] : '',
        'storage' => function_exists('legion_pilot_db_storage_label') && legion_pilot_db_enabled()
            ? legion_pilot_db_storage_label()
            : 'json',
    );
}

function legion_pilot_default_coach_profile_row($coachSlug) {
    $meta = legion_coach_meta($coachSlug);
    $row = array(
        'name' => $meta['name'],
        'photo' => '',
        'rankMarks' => legion_pilot_default_marks(0, 0, 0),
    );
    foreach (legion_pilot_exercise_keys() as $key) {
        $row[$key] = 0;
    }
    return $row;
}

function legion_pilot_normalize_coach_profile_row(array $profile, $coachSlug) {
    $meta = legion_coach_meta($coachSlug);
    $profile['name'] = $meta['name'];
    if (!isset($profile['photo'])) {
        $profile['photo'] = '';
    }
    $profile['rankMarks'] = legion_pilot_athlete_marks($profile);
    foreach (legion_pilot_exercise_keys() as $key) {
        if (!isset($profile[$key]) || !is_numeric($profile[$key])) {
            $profile[$key] = 0;
        } else {
            $profile[$key] = (float) $profile[$key];
        }
    }
    return $profile;
}

function legion_pilot_load_coach_profile($coachSlug = LEGION_PILOT_SLUG) {
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    $profile = null;

    if (function_exists('legion_pilot_db_enabled') && legion_pilot_db_enabled()) {
        if (legion_pilot_db_ensure_ready($coachSlug)) {
            $pdo = legion_pilot_db_pdo();
            if ($pdo) {
                $raw = legion_pilot_db_meta_get($pdo, 'coach_profile', '', $coachSlug);
                if ($raw !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $profile = $decoded;
                    }
                }
            }
        }
    } else {
        $data = storage_read_json(legion_pilot_data_path($coachSlug), null);
        if (is_array($data) && isset($data['coachProfile']) && is_array($data['coachProfile'])) {
            $profile = $data['coachProfile'];
        }
    }

    $meta = legion_coach_meta($coachSlug);
    if (!is_array($profile)) {
        $profile = legion_pilot_default_coach_profile_row($coachSlug);
        legion_pilot_save_coach_profile($coachSlug, $profile);
        return $profile;
    }

    $needsSave = false;
    if (!isset($profile['name']) || $profile['name'] !== $meta['name']) {
        $profile['name'] = $meta['name'];
        $needsSave = true;
    }

    $normalized = legion_pilot_normalize_coach_profile_row($profile, $coachSlug);
    if ($needsSave) {
        legion_pilot_save_coach_profile($coachSlug, $normalized);
    }
    return $normalized;
}

function legion_pilot_save_coach_profile($coachSlug, array $profile) {
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    $profile = legion_pilot_normalize_coach_profile_row($profile, $coachSlug);
    $updatedAt = date('d.m.Y, H:i:s');

    if (function_exists('legion_pilot_db_enabled') && legion_pilot_db_enabled()) {
        $pdo = legion_pilot_db_pdo();
        if (!$pdo) {
            throw new RuntimeException('База данных недоступна');
        }
        legion_pilot_db_ensure_ready($coachSlug);
        legion_pilot_db_meta_set(
            $pdo,
            'coach_profile',
            json_encode($profile, JSON_UNESCAPED_UNICODE),
            $coachSlug
        );
        legion_pilot_db_meta_set($pdo, 'updated_at', $updatedAt, $coachSlug);
        return $profile;
    }

    $data = legion_pilot_load_dataset($coachSlug);
    $data['coachProfile'] = $profile;
    $data['updatedAt'] = $updatedAt;
    legion_pilot_save_dataset($data, false);
    return $profile;
}

function legion_pilot_coach_profile_for_api($coachSlug = LEGION_PILOT_SLUG) {
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    $profile = legion_pilot_load_coach_profile($coachSlug);
    $meta = legion_coach_meta($coachSlug);
    $storedPhoto = isset($profile['photo']) ? (string) $profile['photo'] : '';
    $item = array(
        'name' => $profile['name'],
        'isCoach' => true,
        'coach' => $meta['name'],
        'coachSlug' => $meta['slug'],
        'photo' => legion_pilot_resolve_photo_url($profile['name'], $storedPhoto, $coachSlug),
        'hasPhoto' => legion_pilot_athlete_has_uploaded_photo($storedPhoto, $coachSlug),
        'avatarIndex' => legion_pilot_default_avatar_index($profile['name']),
        'rankMarks' => legion_pilot_athlete_marks($profile),
    );
    foreach (legion_pilot_exercise_keys() as $key) {
        $item[$key] = isset($profile[$key]) && is_numeric($profile[$key]) ? (float) $profile[$key] : 0;
    }
    return $item;
}

function legion_pilot_update_coach_result($exercise, $value, $coachSlug = LEGION_PILOT_SLUG) {
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    $keys = legion_pilot_exercise_keys();
    if (!in_array($exercise, $keys, true)) {
        throw new InvalidArgumentException('Неверное упражнение');
    }
    if (!is_numeric($value)) {
        throw new InvalidArgumentException('Некорректное значение');
    }
    $value = (float) $value;
    if ($value < 0) {
        throw new InvalidArgumentException('Значение не может быть отрицательным');
    }

    $profile = legion_pilot_load_coach_profile($coachSlug);
    $profile[$exercise] = $value;
    legion_pilot_save_coach_profile($coachSlug, $profile);

    return array(
        'updatedAt' => date('d.m.Y, H:i:s'),
        'coachBenchmark' => legion_pilot_coach_profile_for_api($coachSlug),
    );
}

function legion_pilot_update_coach_rank_mark($markIndex, $value, $coachSlug = LEGION_PILOT_SLUG) {
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    $markIndex = (int) $markIndex;
    if ($markIndex < 0 || $markIndex >= 60) {
        throw new InvalidArgumentException('Неверный индекс норматива');
    }
    $value = ((int) $value > 0) ? 1 : 0;

    $profile = legion_pilot_load_coach_profile($coachSlug);
    $marks = legion_pilot_athlete_marks($profile);
    $marks[$markIndex] = $value;
    legion_pilot_apply_league_rollback($marks, $markIndex, $value);
    $profile['rankMarks'] = $marks;
    legion_pilot_save_coach_profile($coachSlug, $profile);

    return array(
        'updatedAt' => date('d.m.Y, H:i:s'),
        'rankMarks' => $marks,
        'coachBenchmark' => legion_pilot_coach_profile_for_api($coachSlug),
    );
}

function legion_pilot_update_result($name, $exercise, $value, $coachSlug = LEGION_PILOT_SLUG) {
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    $keys = legion_pilot_exercise_keys();
    if (!in_array($exercise, $keys, true)) {
        throw new InvalidArgumentException('Неверное упражнение');
    }
    if (!is_numeric($value)) {
        throw new InvalidArgumentException('Некорректное значение');
    }
    $value = (float) $value;
    if ($value < 0) {
        throw new InvalidArgumentException('Значение не может быть отрицательным');
    }

    $data = legion_pilot_load_dataset($coachSlug);
    $idx = legion_pilot_find_athlete_index($data['athletes'], $name);
    if ($idx < 0) {
        throw new InvalidArgumentException('Спортсмен не найден');
    }

    $oldVal = isset($data['athletes'][$idx][$exercise]) ? (float) $data['athletes'][$idx][$exercise] : 0;
    $data['athletes'][$idx][$exercise] = $value;
    legion_pilot_ensure_meta_arrays($data);

    if ($value > $oldVal) {
        $data['history'][] = array(
            'id' => legion_pilot_new_history_id(),
            'date' => legion_pilot_now_ru(),
            'name' => legion_normalize_person_name($name),
            'exercise' => $exercise,
            'oldVal' => $oldVal,
            'newVal' => $value,
            'diff' => $value - $oldVal,
        );
        $data['history'] = legion_pilot_trim_history($data['history']);
    }

    $data = legion_pilot_recompute_achievements($data);
    return legion_pilot_save_dataset($data);
}

function legion_pilot_normalize_birthdate($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if (!$dt || $dt->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException('Некорректная дата рождения');
    }
    $today = new DateTime('today');
    if ($dt > $today) {
        throw new InvalidArgumentException('Дата рождения не может быть в будущем');
    }
    $min = new DateTime('-100 years');
    if ($dt < $min) {
        throw new InvalidArgumentException('Слишком ранняя дата рождения');
    }
    return $value;
}

function legion_pilot_compute_age($birthdate) {
    if ($birthdate === null || $birthdate === '') {
        return null;
    }
    try {
        $born = new DateTime((string) $birthdate);
        $today = new DateTime('today');
        return (int) $born->diff($today)->y;
    } catch (Exception $e) {
        return null;
    }
}

function legion_pilot_update_birthdate($name, $birthdate, $coachSlug = LEGION_PILOT_SLUG) {
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    $normalized = legion_pilot_normalize_birthdate($birthdate);

    $data = legion_pilot_load_dataset($coachSlug);
    $idx = legion_pilot_find_athlete_index($data['athletes'], $name);
    if ($idx < 0) {
        throw new InvalidArgumentException('Спортсмен не найден');
    }

    $data['athletes'][$idx]['birthdate'] = $normalized;
    return legion_pilot_save_dataset($data);
}

function legion_pilot_name_base($name) {
    $name = legion_normalize_person_name($name);
    if ($name === '') {
        return '';
    }
    $parts = preg_split('/\s+/u', $name);
    if (count($parts) >= 2) {
        return $parts[0] . ' ' . $parts[1];
    }
    return $name;
}

function legion_pilot_name_is_base_only($name) {
    $name = legion_normalize_person_name($name);
    return $name !== '' && $name === legion_pilot_name_base($name);
}

function legion_pilot_group_has_base_name(array $athletes, $baseName) {
    $normBase = legion_normalize_person_name($baseName);
    if ($normBase === '') {
        return false;
    }
    foreach ($athletes as $row) {
        if (!is_array($row) || empty($row['name'])) {
            continue;
        }
        $rowBase = legion_pilot_name_base($row['name']);
        if (legion_normalize_person_name($rowBase) === $normBase) {
            return true;
        }
    }
    return false;
}

function legion_pilot_append_patronymic_initial($name, $letter) {
    $base = legion_pilot_name_base($name);
    if ($base === '') {
        throw new InvalidArgumentException('Укажите ФИО');
    }
    $letter = trim((string) $letter);
    if ($letter === '') {
        throw new InvalidArgumentException('Укажите первую букву отчества');
    }
    if (function_exists('mb_substr')) {
        $letter = mb_strtoupper(mb_substr($letter, 0, 1, 'UTF-8'), 'UTF-8');
    } else {
        $letter = strtoupper(substr($letter, 0, 1));
    }
    if (!preg_match('/^\p{L}$/u', $letter)) {
        throw new InvalidArgumentException('Укажите букву отчества');
    }
    return legion_normalize_person_name($base . ' ' . $letter . '.');
}

function legion_pilot_assert_can_add_athlete_name(array $athletes, $name) {
    $name = legion_normalize_person_name($name);
    if ($name === '') {
        throw new InvalidArgumentException('Укажите ФИО');
    }

    if (legion_pilot_find_athlete_index($athletes, $name) >= 0) {
        if (legion_pilot_name_is_base_only($name)) {
            throw new LegionPilotNeedsPatronymicException(legion_pilot_name_base($name));
        }
        throw new InvalidArgumentException('Спортсмен уже есть в группе');
    }

    $base = legion_pilot_name_base($name);
    if (legion_pilot_name_is_base_only($name) && legion_pilot_group_has_base_name($athletes, $base)) {
        throw new LegionPilotNeedsPatronymicException($base);
    }

    return $name;
}

function legion_pilot_add_athlete($name, $coachSlug = LEGION_PILOT_SLUG, $patronymicInitial = '', $birthdate = null) {
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    $name = legion_normalize_person_name($name);
    if (trim((string) $patronymicInitial) !== '') {
        $name = legion_pilot_append_patronymic_initial($name, $patronymicInitial);
    }

    $data = legion_pilot_load_dataset($coachSlug);
    $name = legion_pilot_assert_can_add_athlete_name($data['athletes'], $name);

    $row = array(
        'name' => $name,
        'photo' => '',
        'rankMarks' => legion_pilot_default_marks(0, 0, 0),
    );
    if ($birthdate !== null && trim((string) $birthdate) !== '') {
        $row['birthdate'] = legion_pilot_normalize_birthdate($birthdate);
    }
    foreach (legion_pilot_exercise_keys() as $key) {
        $row[$key] = 0;
    }
    $data['athletes'][] = $row;
    return legion_pilot_save_dataset($data);
}

function legion_pilot_parse_athlete_name_list($text) {
    $text = preg_replace('/^\xEF\xBB\xBF/', '', (string) $text);
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $names = array();
    $seen = array();

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (strpos($line, "\t") !== false) {
            $parts = explode("\t", $line);
            $line = trim($parts[0]);
        }
        if ($line === '') {
            continue;
        }
        $lower = function_exists('mb_strtolower') ? mb_strtolower($line, 'UTF-8') : strtolower($line);
        if (preg_match('/^(фио|имя|ф\.?\s*и\.?\s*о\.?|name|спортсмен)\b/u', $lower)) {
            continue;
        }
        $norm = legion_normalize_person_name($line);
        if ($norm === '' || isset($seen[$norm])) {
            continue;
        }
        $seen[$norm] = true;
        $names[] = $norm;
    }

    return $names;
}

function legion_pilot_add_athletes_from_list($text, $coachSlug = LEGION_PILOT_SLUG) {
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    $names = legion_pilot_parse_athlete_name_list($text);
    if (empty($names)) {
        throw new InvalidArgumentException('Не найдено ни одного ФИО. Вставьте список — по одному имени на строку.');
    }

    $data = legion_pilot_load_dataset($coachSlug);
    $added = array();
    $skipped = array();
    $needsPatronymic = array();
    $needsPatronymicSeen = array();

    foreach ($names as $name) {
        if (legion_pilot_find_athlete_index($data['athletes'], $name) >= 0) {
            if (legion_pilot_name_is_base_only($name)) {
                $base = legion_pilot_name_base($name);
                if (!isset($needsPatronymicSeen[$base])) {
                    $needsPatronymicSeen[$base] = true;
                    $needsPatronymic[] = $base;
                }
            } else {
                $skipped[] = $name;
            }
            continue;
        }

        $base = legion_pilot_name_base($name);
        if (legion_pilot_name_is_base_only($name) && legion_pilot_group_has_base_name($data['athletes'], $base)) {
            if (!isset($needsPatronymicSeen[$base])) {
                $needsPatronymicSeen[$base] = true;
                $needsPatronymic[] = $base;
            }
            continue;
        }

        $row = array(
            'name' => $name,
            'photo' => '',
            'rankMarks' => legion_pilot_default_marks(0, 0, 0),
        );
        foreach (legion_pilot_exercise_keys() as $key) {
            $row[$key] = 0;
        }
        $data['athletes'][] = $row;
        $added[] = $name;
    }

    if (!empty($added)) {
        $data = legion_pilot_save_dataset($data);
    }

    return array(
        'added' => $added,
        'skipped' => $skipped,
        'needsPatronymic' => $needsPatronymic,
        'total' => count($names),
        'updatedAt' => isset($data['updatedAt']) ? $data['updatedAt'] : legion_pilot_now_ru(),
    );
}

function legion_pilot_remove_athlete($name, $coachSlug = LEGION_PILOT_SLUG) {
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    $data = legion_pilot_load_dataset($coachSlug);
    $idx = legion_pilot_find_athlete_index($data['athletes'], $name);
    if ($idx < 0) {
        throw new InvalidArgumentException('Спортсмен не найден');
    }
    array_splice($data['athletes'], $idx, 1);
    return legion_pilot_save_dataset($data);
}

function legion_pilot_update_rank_mark($name, $markIndex, $value, $coachSlug = LEGION_PILOT_SLUG) {
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    $markIndex = (int) $markIndex;
    if ($markIndex < 0 || $markIndex >= 60) {
        throw new InvalidArgumentException('Неверный индекс норматива');
    }
    $value = ((int) $value > 0) ? 1 : 0;

    $data = legion_pilot_load_dataset($coachSlug);
    $idx = legion_pilot_find_athlete_index($data['athletes'], $name);
    if ($idx < 0) {
        throw new InvalidArgumentException('Спортсмен не найден');
    }

    $marks = legion_pilot_athlete_marks($data['athletes'][$idx]);
    $oldVal = (int) $marks[$markIndex];
    $marks[$markIndex] = $value;
    legion_pilot_apply_league_rollback($marks, $markIndex, $value);
    $newVal = (int) $marks[$markIndex];
    $data['athletes'][$idx]['rankMarks'] = $marks;

    require_once __DIR__ . '/history_lib.php';
    legion_log_rank_mark_change($name, $markIndex, $oldVal, $newVal);

    $data = legion_pilot_recompute_achievements($data);
    $saved = legion_pilot_save_dataset($data);
    $saved['lastRankMarks'] = $marks;
    return $saved;
}

function legion_pilot_normalize_sheets_csv_url($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    if (stripos($url, 'output=csv') !== false) {
        return $url;
    }
    if (preg_match('#docs\.google\.com/spreadsheets/d/e/([^/]+)/pub#', $url, $m)) {
        $gid = '0';
        if (preg_match('/[?&#]gid=(\d+)/', $url, $g)) {
            $gid = $g[1];
        }
        return 'https://docs.google.com/spreadsheets/d/e/' . $m[1] . '/pub?gid=' . $gid . '&single=true&output=csv';
    }
    return $url;
}

/**
 * Строка тренера из Google Таблицы (первая строка с результатами + ранг из таблицы рангов).
 *
 * @return array|null
 */
function legion_pilot_fetch_coach_benchmark_from_sheets($coachSlug) {
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    if ($coachSlug === LEGION_PILOT_SLUG) {
        return null;
    }

    $coaches = legion_coaches_config();
    if (!isset($coaches[$coachSlug]) || empty($coaches[$coachSlug]['csvUrl'])) {
        return null;
    }

    require_once __DIR__ . '/results_lib.php';
    require_once __DIR__ . '/sheets_cache_lib.php';
    require_once __DIR__ . '/ranks_lib.php';

    $coachCfg = $coaches[$coachSlug];
    $resultsUrl = legion_pilot_normalize_sheets_csv_url($coachCfg['csvUrl']);
    $resultsFetch = legion_fetch_sheet_cached($resultsUrl, 120);
    if (empty($resultsFetch['ok']) || empty($resultsFetch['body'])) {
        return null;
    }

    $parsed = legion_parse_results_csv($resultsFetch['body']);
    if (empty($parsed)) {
        return null;
    }

    $coachRow = null;
    foreach ($parsed as $row) {
        if (!empty($row['isCoach'])) {
            $coachRow = $row;
            break;
        }
    }
    if (!$coachRow || empty($coachRow['name'])) {
        return null;
    }

    $meta = legion_coach_meta($coachSlug);
    $benchmark = array(
        'name' => $coachRow['name'],
        'isCoach' => true,
        'coach' => $meta['name'],
        'coachSlug' => $coachSlug,
        'photo' => isset($coachRow['photo']) ? (string) $coachRow['photo'] : '',
        'rankMarks' => legion_pilot_default_marks(0, 0, 0),
    );
    foreach (legion_pilot_exercise_keys() as $key) {
        $benchmark[$key] = isset($coachRow[$key]) && is_numeric($coachRow[$key]) ? (float) $coachRow[$key] : 0;
    }

    $ranksUrl = isset($coachCfg['ranksCsvUrl']) ? trim((string) $coachCfg['ranksCsvUrl']) : '';
    if ($ranksUrl !== '') {
        $ranksUrl = legion_pilot_normalize_sheets_csv_url($ranksUrl);
        $ranksFetch = legion_fetch_sheet_cached($ranksUrl, 120);
        if (!empty($ranksFetch['ok']) && !empty($ranksFetch['body'])) {
            $entries = legion_parse_rank_csv_entries($ranksFetch['body']);
            $namesToMatch = array(
                legion_normalize_person_name($benchmark['name']),
                legion_normalize_person_name($coachCfg['name']),
            );
            foreach ($entries as $entry) {
                $entryNorm = legion_normalize_person_name(isset($entry['name']) ? $entry['name'] : '');
                if ($entryNorm === '' || !in_array($entryNorm, $namesToMatch, true)) {
                    continue;
                }
                $benchmark['rankMarks'] = legion_pilot_normalize_marks($entry['marks']);
                break;
            }
        }
    }

    return $benchmark;
}

function legion_pilot_import_from_sheets($resultsUrl, $ranksUrl = '', $keepHistory = true, $coachSlug = LEGION_PILOT_SLUG) {
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    require_once __DIR__ . '/results_lib.php';
    require_once __DIR__ . '/sheets_cache_lib.php';

    $resultsUrl = legion_pilot_normalize_sheets_csv_url($resultsUrl);
    if ($resultsUrl === '') {
        throw new InvalidArgumentException('Укажите ссылку на таблицу результатов');
    }

    $resultsFetch = legion_fetch_sheet_cached($resultsUrl, 0);
    if (empty($resultsFetch['ok'])) {
        $msg = isset($resultsFetch['error']) ? $resultsFetch['error'] : 'неизвестная ошибка';
        throw new RuntimeException('Не удалось загрузить таблицу результатов: ' . $msg);
    }

    $parsed = legion_parse_results_csv($resultsFetch['body']);
    if (empty($parsed)) {
        throw new RuntimeException('В таблице результатов нет данных (проверьте заголовки и формат CSV)');
    }

    $coachNames = array();
    $athletes = array();
    foreach ($parsed as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (!empty($row['isCoach'])) {
            continue;
        }
        if (empty($row['name'])) {
            continue;
        }
        $coachNames[] = $row['name'];
        $item = array(
            'name' => $row['name'],
            'photo' => isset($row['photo']) ? (string) $row['photo'] : '',
            'rankMarks' => legion_pilot_default_marks(0, 0, 0),
        );
        foreach (legion_pilot_exercise_keys() as $key) {
            $item[$key] = isset($row[$key]) && is_numeric($row[$key]) ? (float) $row[$key] : 0;
        }
        $athletes[] = $item;
    }

    if (empty($athletes)) {
        throw new RuntimeException('После пропуска строки тренера не осталось спортсменов');
    }

    $withRanks = 0;
    $ranksUrl = trim((string) $ranksUrl);
    if ($ranksUrl !== '') {
        $ranksUrl = legion_pilot_normalize_sheets_csv_url($ranksUrl);
        $ranksFetch = legion_fetch_sheet_cached($ranksUrl, 0);
        if (empty($ranksFetch['ok'])) {
            $msg = isset($ranksFetch['error']) ? $ranksFetch['error'] : 'неизвестная ошибка';
            throw new RuntimeException('Не удалось загрузить таблицу рангов: ' . $msg);
        }
        $entries = legion_parse_rank_csv_entries($ranksFetch['body']);
        $rankMap = legion_merge_coach_rank_entries($entries, $coachNames);
        foreach ($athletes as $i => $athlete) {
            $norm = legion_normalize_person_name($athlete['name']);
            if ($norm === '' || !isset($rankMap[$norm])) {
                continue;
            }
            $athletes[$i]['rankMarks'] = legion_pilot_normalize_marks($rankMap[$norm]);
            if (legion_count_rank_marks($rankMap[$norm]) > 0) {
                $withRanks++;
            }
        }
    }

    $data = legion_pilot_load_dataset($coachSlug);
    legion_pilot_ensure_meta_arrays($data);
    if (!$keepHistory) {
        $data['history'] = array();
        $data['achievements'] = array();
    }

    $data['athletes'] = $athletes;
    $data = legion_pilot_recompute_achievements($data);
    legion_pilot_save_dataset($data);

    return array(
        'athletes' => count($athletes),
        'withRanks' => $withRanks,
        'keepHistory' => (bool) $keepHistory,
        'resultsUrl' => $resultsUrl,
        'ranksUrl' => $ranksUrl,
    );
}

require_once __DIR__ . '/pilot_db_lib.php';
