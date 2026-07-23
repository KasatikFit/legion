<?php
require_once dirname(__DIR__) . '/_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    legion_mb_fail(405, 'Метод не поддерживается');
}

$expected = legion_mb_admin_password();
if ($expected === null) {
    legion_mb_fail(503, 'Создайте api/events_admin_config.php из example');
}

$payload = legion_mb_read_json();
$password = isset($payload['password']) ? (string) $payload['password'] : '';
if (!hash_equals($expected, $password)) {
    legion_mb_fail(401, 'Неверный пароль админа');
}

$pdo = legion_mb_pdo();
$token = legion_mb_create_session($pdo, 'admin', null);

legion_mb_ok(array('success' => true, 'token' => $token));
