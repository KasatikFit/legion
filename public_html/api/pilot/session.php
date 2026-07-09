<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/pilot_lib.php';

echo json_encode(array(
    'authenticated' => legion_pilot_is_authenticated(),
    'authConfigured' => legion_pilot_auth_is_configured(),
), JSON_UNESCAPED_UNICODE);
