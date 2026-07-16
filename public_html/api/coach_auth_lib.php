<?php

require_once __DIR__ . '/coaches.php';
require_once __DIR__ . '/coaches_lib.php';
require_once __DIR__ . '/coach_data_lib.php';

function legion_coach_auth_config_path() {
    return __DIR__ . '/coach_auth.php';
}

function legion_coach_auth_map() {
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $map = array();
    $path = legion_coach_auth_config_path();
    if (is_file($path)) {
        $loaded = require $path;
        if (is_array($loaded)) {
            $map = $loaded;
        }
    }
    return $map;
}

function legion_coach_auth_is_configured($coachSlug) {
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    if (legion_coach_auth_hash_from_db($coachSlug) !== '') {
        return true;
    }
    $map = legion_coach_auth_map();
    if (isset($map[$coachSlug]) && is_array($map[$coachSlug])) {
        $entry = $map[$coachSlug];
        if (!empty($entry['password_hash']) || !empty($entry['password'])) {
            return true;
        }
    }
    if ($coachSlug === 'pilot-demo' && is_file(__DIR__ . '/pilot_auth.php')) {
        return true;
    }
    return false;
}

function legion_coach_verify_password($coachSlug, $password) {
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    $hash = legion_coach_auth_hash_from_db($coachSlug);
    if ($hash !== '' && password_verify((string) $password, $hash)) {
        return true;
    }
    $map = legion_coach_auth_map();
    if (isset($map[$coachSlug]) && is_array($map[$coachSlug])) {
        $entry = $map[$coachSlug];
        if (!empty($entry['password_hash'])) {
            return password_verify((string) $password, (string) $entry['password_hash']);
        }
        if (isset($entry['password'])) {
            return hash_equals((string) $entry['password'], (string) $password);
        }
    }
    if ($coachSlug === 'pilot-demo' && function_exists('legion_pilot_verify_password')) {
        return legion_pilot_verify_password($password);
    }
    return false;
}

function legion_coach_session_start() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function legion_coach_is_authenticated($coachSlug) {
    legion_coach_session_start();
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    if (empty($_SESSION['legion_coach_auth']) || !is_array($_SESSION['legion_coach_auth'])) {
        return false;
    }
    return !empty($_SESSION['legion_coach_auth'][$coachSlug]);
}

function legion_coach_set_authenticated($coachSlug, $authenticated) {
    legion_coach_session_start();
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    if (!isset($_SESSION['legion_coach_auth']) || !is_array($_SESSION['legion_coach_auth'])) {
        $_SESSION['legion_coach_auth'] = array();
    }
    if ($authenticated) {
        $_SESSION['legion_coach_auth'][$coachSlug] = true;
    } else {
        unset($_SESSION['legion_coach_auth'][$coachSlug]);
    }
}

function legion_coach_require_auth_json($coachSlug) {
    if (!legion_coach_is_authenticated($coachSlug)) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('error' => 'Требуется вход'), JSON_UNESCAPED_UNICODE);
        exit;
    }
}
