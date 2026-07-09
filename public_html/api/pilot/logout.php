<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/pilot_lib.php';

legion_pilot_session_start();
unset($_SESSION['legion_pilot_auth']);

echo json_encode(array('success' => true), JSON_UNESCAPED_UNICODE);
