<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_lib.php';

$coach = legion_coach_json_slug_or_exit();
legion_coach_require_auth_json($coach);

$list = array();
if ($coach === LEGION_PILOT_SLUG) {
    $list[] = array(
        'slug' => LEGION_PILOT_SLUG,
        'name' => 'Пилотная группа',
        'resultsUrl' => '',
        'ranksUrl' => '',
    );
} else {
    $coaches = legion_coaches_config();
    if (isset($coaches[$coach])) {
        $entry = $coaches[$coach];
        $list[] = array(
            'slug' => $coach,
            'name' => $entry['name'],
            'resultsUrl' => isset($entry['csvUrl']) ? $entry['csvUrl'] : '',
            'ranksUrl' => isset($entry['ranksCsvUrl']) ? $entry['ranksCsvUrl'] : '',
        );
    }
}

echo json_encode(array(
    'ok' => true,
    'coaches' => $list,
), JSON_UNESCAPED_UNICODE);
