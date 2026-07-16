<?php

function legion_admin_auth_config_path() {
    return __DIR__ . '/admin_auth.php';
}

function legion_admin_auth_is_configured() {
    if (!is_file(legion_admin_auth_config_path())) {
        return false;
    }
    require_once legion_admin_auth_config_path();
    if (defined('LEGION_ADMIN_PASSWORD_HASH') && LEGION_ADMIN_PASSWORD_HASH !== '') {
        return true;
    }
    return defined('LEGION_ADMIN_PASSWORD') && LEGION_ADMIN_PASSWORD !== '';
}

function legion_admin_verify_password($password) {
    if (!legion_admin_auth_is_configured()) {
        return false;
    }
    require_once legion_admin_auth_config_path();
    if (defined('LEGION_ADMIN_PASSWORD_HASH') && LEGION_ADMIN_PASSWORD_HASH !== '') {
        return password_verify((string) $password, (string) LEGION_ADMIN_PASSWORD_HASH);
    }
    if (defined('LEGION_ADMIN_PASSWORD')) {
        return hash_equals((string) LEGION_ADMIN_PASSWORD, (string) $password);
    }
    return false;
}

function legion_admin_session_start() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function legion_admin_is_authenticated() {
    legion_admin_session_start();
    return !empty($_SESSION['legion_admin_auth']);
}

function legion_admin_set_authenticated($authenticated) {
    legion_admin_session_start();
    $_SESSION['legion_admin_auth'] = $authenticated ? true : false;
}

function legion_admin_require_auth_json() {
    if (!legion_admin_is_authenticated()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('error' => 'Требуется вход суперадмина'), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function legion_admin_require_db_json() {
    require_once __DIR__ . '/coaches_lib.php';
    $pdo = legion_coaches_db_pdo();
    if (!$pdo instanceof PDO) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'error' => 'MySQL недоступен — настройте api/pilot_db_config.php',
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }
}
