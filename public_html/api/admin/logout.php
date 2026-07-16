<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/admin_auth_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('error' => 'Метод не поддерживается'), JSON_UNESCAPED_UNICODE);
    exit;
}

legion_admin_set_authenticated(false);
echo json_encode(array('success' => true), JSON_UNESCAPED_UNICODE);
