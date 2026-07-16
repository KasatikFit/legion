<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/admin_auth_lib.php';

legion_admin_require_auth_json();
legion_admin_require_db_json();

require_once dirname(__DIR__) . '/coaches_lib.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    echo json_encode(array(
        'success' => true,
        'coaches' => legion_coaches_admin_list(),
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = array();
}

if ($method === 'POST') {
    $name = isset($payload['name']) ? (string) $payload['name'] : '';
    $password = isset($payload['password']) ? (string) $payload['password'] : '';
    $options = array();
    if (isset($payload['slug'])) {
        $options['slug'] = (string) $payload['slug'];
    }
    if (isset($payload['tagline'])) {
        $options['tagline'] = (string) $payload['tagline'];
    }
    try {
        $created = legion_coaches_create($name, $password, $options);
        echo json_encode(array('success' => true, 'coach' => $created), JSON_UNESCAPED_UNICODE);
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($method === 'PATCH') {
    $slug = isset($payload['slug']) ? (string) $payload['slug'] : '';
    if ($slug === '') {
        http_response_code(400);
        echo json_encode(array('error' => 'Укажите slug'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    $fields = array();
    if (isset($payload['name'])) {
        $fields['name'] = (string) $payload['name'];
    }
    if (isset($payload['tagline'])) {
        $fields['tagline'] = (string) $payload['tagline'];
    }
    if (array_key_exists('isVisible', $payload)) {
        $fields['isVisible'] = !empty($payload['isVisible']);
    }
    if (isset($payload['password'])) {
        $fields['password'] = (string) $payload['password'];
    }
    try {
        legion_coaches_update($slug, $fields);
        if (!empty($fields['name']) || isset($fields['isVisible'])) {
            legion_coach_provision_files($slug);
        }
        echo json_encode(array('success' => true), JSON_UNESCAPED_UNICODE);
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
    }
    exit;
}

http_response_code(405);
echo json_encode(array('error' => 'Метод не поддерживается'), JSON_UNESCAPED_UNICODE);
