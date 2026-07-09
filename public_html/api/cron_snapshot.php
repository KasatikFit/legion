<?php
/**
 * Ежедневный снимок результатов (cron).
 *
 * Пример cron (06:00 каждый день):
 * curl -fsS "https://ваш-домен.ru/api/cron_snapshot.php?key=ВАШ_СЕКРЕТ"
 *
 * Скопируйте api/cron_config.example.php → api/cron_config.php и задайте ключ.
 * Не заливайте cron_config.php в публичный репозиторий.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/history_lib.php';

$expectedKey = legion_cron_snapshot_key();
$providedKey = isset($_GET['key']) ? (string) $_GET['key'] : '';
if ($providedKey === '' && isset($_POST['key'])) {
    $providedKey = (string) $_POST['key'];
}

if ($expectedKey === '' || !hash_equals($expectedKey, $providedKey)) {
    http_response_code(403);
    echo json_encode(array('error' => 'Доступ запрещён'), JSON_UNESCAPED_UNICODE);
    exit;
}

$scope = storage_validate_scope(isset($_GET['scope']) ? $_GET['scope'] : 'global');
if ($scope === null) {
    http_response_code(400);
    echo json_encode(array('error' => 'Неверный scope'), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $result = legion_run_daily_snapshot($scope);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'error' => $e->getMessage(),
    ), JSON_UNESCAPED_UNICODE);
}
