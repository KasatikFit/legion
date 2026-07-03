<?php
/**
 * Список тренеров клуба — единый источник для PHP и JavaScript.
 * При добавлении тренера — только этот файл (+ папка /{slug}/index.php).
 */
function legion_coaches_config() {
    return array(
        'yakutin' => array(
            'name' => 'Якутин Иван',
            'tagline' => 'Группа тренера',
            'csvUrl' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSf6qfw-eRUUYB0zEnlyCh4y0gq731_RdLUT7AJ54ApwaV3N7_4KbFIbOVLlx5u1mpL0NY7M4JbsDjj/pub?gid=999564821&single=true&output=csv',
            'ranksCsvUrl' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSf6qfw-eRUUYB0zEnlyCh4y0gq731_RdLUT7AJ54ApwaV3N7_4KbFIbOVLlx5u1mpL0NY7M4JbsDjj/pub?gid=2106158359&single=true&output=csv',
        ),
        'nikonov' => array(
            'name' => 'Никонов Никита',
            'tagline' => 'Группа тренера',
            'csvUrl' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSf6qfw-eRUUYB0zEnlyCh4y0gq731_RdLUT7AJ54ApwaV3N7_4KbFIbOVLlx5u1mpL0NY7M4JbsDjj/pub?gid=2018595165&single=true&output=csv',
            'ranksCsvUrl' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSf6qfw-eRUUYB0zEnlyCh4y0gq731_RdLUT7AJ54ApwaV3N7_4KbFIbOVLlx5u1mpL0NY7M4JbsDjj/pub?gid=1177372140&single=true&output=csv',
        ),
        'kasatkin' => array(
            'name' => 'Касаткин Алексей',
            'tagline' => 'Группа тренера',
            'csvUrl' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSf6qfw-eRUUYB0zEnlyCh4y0gq731_RdLUT7AJ54ApwaV3N7_4KbFIbOVLlx5u1mpL0NY7M4JbsDjj/pub?gid=0&single=true&output=csv',
            'ranksCsvUrl' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSf6qfw-eRUUYB0zEnlyCh4y0gq731_RdLUT7AJ54ApwaV3N7_4KbFIbOVLlx5u1mpL0NY7M4JbsDjj/pub?gid=2130784782&single=true&output=csv',
        ),
        'parkhaev' => array(
            'name' => 'Пархаев Алексей',
            'tagline' => 'Группа тренера',
            'csvUrl' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSf6qfw-eRUUYB0zEnlyCh4y0gq731_RdLUT7AJ54ApwaV3N7_4KbFIbOVLlx5u1mpL0NY7M4JbsDjj/pub?gid=1103251903&single=true&output=csv',
            'ranksCsvUrl' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSf6qfw-eRUUYB0zEnlyCh4y0gq731_RdLUT7AJ54ApwaV3N7_4KbFIbOVLlx5u1mpL0NY7M4JbsDjj/pub?gid=1582437394&single=true&output=csv',
        ),
        'makarenkov' => array(
            'name' => 'Макаренков Артём',
            'tagline' => 'Группа тренера',
            'csvUrl' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSf6qfw-eRUUYB0zEnlyCh4y0gq731_RdLUT7AJ54ApwaV3N7_4KbFIbOVLlx5u1mpL0NY7M4JbsDjj/pub?gid=573257096&single=true&output=csv',
            'ranksCsvUrl' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSf6qfw-eRUUYB0zEnlyCh4y0gq731_RdLUT7AJ54ApwaV3N7_4KbFIbOVLlx5u1mpL0NY7M4JbsDjj/pub?gid=287377539&single=true&output=csv',
        ),
    );
}

function legion_coaches_for_js() {
    $list = array();
    foreach (legion_coaches_config() as $slug => $coach) {
        $list[] = array(
            'slug' => $slug,
            'name' => $coach['name'],
            'csvUrl' => $coach['csvUrl'],
            'ranksCsvUrl' => isset($coach['ranksCsvUrl']) ? $coach['ranksCsvUrl'] : '',
        );
    }
    return $list;
}

function legion_coach_nav_icon() {
    return '🏋️';
}

function legion_coach_slug_from_script() {
    if (!empty($GLOBALS['LEGION_COACH_SLUG'])) {
        return $GLOBALS['LEGION_COACH_SLUG'];
    }

    $coaches = legion_coaches_config();
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $path = parse_url($uri, PHP_URL_PATH);
    if (!$path) {
        $path = '';
    }
    $segments = array_values(array_filter(explode('/', trim($path, '/'))));
    foreach ($segments as $segment) {
        if (isset($coaches[$segment])) {
            return $segment;
        }
    }

    $candidates = array();
    if (!empty($_SERVER['SCRIPT_FILENAME'])) {
        $candidates[] = basename(dirname($_SERVER['SCRIPT_FILENAME']));
    }
    if (!empty($_SERVER['SCRIPT_NAME'])) {
        $candidates[] = basename(dirname($_SERVER['SCRIPT_NAME']));
    }

    foreach ($candidates as $dir) {
        if (isset($coaches[$dir])) {
            return $dir;
        }
    }

    return !empty($candidates[0]) ? $candidates[0] : '';
}

function legion_valid_storage_scopes($includeGlobal = true) {
    $scopes = array_keys(legion_coaches_config());
    if ($includeGlobal) {
        array_unshift($scopes, 'global');
    }
    return $scopes;
}
