<?php

/**
 * Список тренеров клуба — загрузка из MySQL (таблица legion_coaches).
 * При первом запуске данные копируются из coaches_legacy.php.
 */
require_once __DIR__ . '/coaches_lib.php';

function legion_coaches_for_js() {
    $list = array();
    foreach (legion_coaches_config() as $slug => $coach) {
        $list[] = array(
            'slug' => $slug,
            'name' => $coach['name'],
            'csvUrl' => $coach['csvUrl'],
            'ranksCsvUrl' => isset($coach['ranksCsvUrl']) ? $coach['ranksCsvUrl'] : '',
            'storage' => isset($coach['storage']) ? $coach['storage'] : 'mysql',
        );
    }
    return $list;
}

function legion_coach_uses_mysql($slug) {
    $coaches = legion_coaches_registry();
    if (!isset($coaches[$slug])) {
        return false;
    }
    $storage = isset($coaches[$slug]['storage']) ? $coaches[$slug]['storage'] : 'mysql';
    return $storage === 'mysql';
}

function legion_coach_nav_icon() {
    return '🏋️';
}

function legion_coach_slug_from_script() {
    if (!empty($GLOBALS['LEGION_COACH_SLUG'])) {
        return $GLOBALS['LEGION_COACH_SLUG'];
    }

    $coaches = legion_coaches_registry();
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
    $scopes = array_keys(legion_coaches_registry());
    if ($includeGlobal) {
        array_unshift($scopes, 'global');
    }
    return $scopes;
}
