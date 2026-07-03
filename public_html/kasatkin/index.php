<?php
$LEGION_COACH_SLUG = basename(__DIR__);
$GLOBALS['LEGION_COACH_SLUG'] = $LEGION_COACH_SLUG;
$LEGION_PAGE = 'coach';
$coachPage = dirname(__DIR__) . '/coach-page.php';
if (!is_file($coachPage)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Ошибка 500</h1><p>Не найден файл coach-page.php. Загрузите его в корень public_html через WinSCP.</p>';
    exit;
}
require $coachPage;
