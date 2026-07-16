<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/admin_auth_lib.php';

legion_admin_require_auth_json();
legion_admin_require_db_json();

require_once dirname(__DIR__) . '/coaches_lib.php';

$name = isset($_GET['name']) ? trim((string) $_GET['name']) : '';
$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';

if ($slug !== '') {
    try {
        $slug = legion_coaches_normalize_slug_input($slug);
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        exit;
    }
    $pdo = legion_coaches_db_pdo();
    if (legion_coaches_slug_exists($pdo, $slug)) {
        http_response_code(400);
        echo json_encode(array('error' => 'Slug уже занят'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(array('slug' => $slug), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($name === '') {
    http_response_code(400);
    echo json_encode(array('error' => 'Укажите name или slug'), JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = legion_coaches_db_pdo();
$slug = legion_coach_suggest_slug($name, $pdo);
echo json_encode(array('slug' => $slug), JSON_UNESCAPED_UNICODE);
