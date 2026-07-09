<?php

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/pilot_lib.php';
require_once dirname(__DIR__) . '/coaches.php';

legion_pilot_require_auth_json();

$list = array();
foreach (legion_coaches_config() as $slug => $coach) {
    $list[] = array(
        'slug' => $slug,
        'name' => $coach['name'],
        'resultsUrl' => isset($coach['csvUrl']) ? $coach['csvUrl'] : '',
        'ranksUrl' => isset($coach['ranksCsvUrl']) ? $coach['ranksCsvUrl'] : '',
    );
}

echo json_encode(array(
    'ok' => true,
    'coaches' => $list,
), JSON_UNESCAPED_UNICODE);
