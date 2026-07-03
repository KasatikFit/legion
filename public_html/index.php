<?php
$clubPage = __DIR__ . '/club-page.php';
if (!is_file($clubPage)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Ошибка 500</h1><p>Не найден файл club-page.php на сервере. Загрузите его через WinSCP.</p>';
    exit;
}
require $clubPage;
