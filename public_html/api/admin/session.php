<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/admin_auth_lib.php';

echo json_encode(array(
    'authenticated' => legion_admin_is_authenticated(),
    'configured' => legion_admin_auth_is_configured(),
), JSON_UNESCAPED_UNICODE);
