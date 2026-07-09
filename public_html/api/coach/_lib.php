<?php

require_once dirname(__DIR__) . '/pilot_lib.php';

function legion_coach_api_require_mysql($coachSlug) {
    if ($coachSlug === LEGION_PILOT_SLUG || legion_coach_uses_mysql($coachSlug)) {
        return;
    }
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'error' => 'У этого тренера данные ещё в Google Таблицах. Включите storage => mysql в api/coaches.php',
    ), JSON_UNESCAPED_UNICODE);
    exit;
}
