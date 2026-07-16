<?php

require_once __DIR__ . '/coaches.php';

define('LEGION_PILOT_DEMO_SLUG', 'pilot-demo');

function legion_coach_normalize_slug($slug) {
    $slug = strtolower(trim((string) $slug));
    $slug = preg_replace('/[^a-z0-9\-_]/', '', $slug);
    if ($slug === '') {
        throw new InvalidArgumentException('Не указан тренер');
    }
    if ($slug === LEGION_PILOT_DEMO_SLUG) {
        return $slug;
    }
    $coaches = legion_coaches_registry();
    if (!isset($coaches[$slug])) {
        throw new InvalidArgumentException('Неизвестный тренер: ' . $slug);
    }
    return $slug;
}

function legion_coach_meta($slug) {
    $slug = legion_coach_normalize_slug($slug);
    if ($slug === LEGION_PILOT_DEMO_SLUG) {
        return array(
            'slug' => $slug,
            'name' => 'Пилотная группа',
            'tagline' => 'Тестовый режим — не отображается в общем списке тренеров',
            'storage' => 'mysql',
        );
    }
    $coaches = legion_coaches_registry();
    $coach = $coaches[$slug];
    return array(
        'slug' => $slug,
        'name' => $coach['name'],
        'tagline' => isset($coach['tagline']) ? $coach['tagline'] : 'Группа тренера',
        'storage' => isset($coach['storage']) ? $coach['storage'] : 'mysql',
    );
}

function legion_coach_slug_from_request() {
    $slug = '';
    if (isset($_GET['coach'])) {
        $slug = (string) $_GET['coach'];
    }
    if ($slug === '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['coach'])) {
            $slug = (string) $_POST['coach'];
        } else {
            $payload = json_decode(file_get_contents('php://input'), true);
            if (is_array($payload) && isset($payload['coach'])) {
                $slug = (string) $payload['coach'];
            }
        }
    }
    return legion_coach_normalize_slug($slug);
}

function legion_coach_json_slug_or_exit() {
    try {
        return legion_coach_slug_from_request();
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function legion_coach_data_json_path($slug) {
    $slug = legion_coach_normalize_slug($slug);
    return __DIR__ . '/data/coaches/' . $slug . '.json';
}

function legion_coach_photos_dir($slug) {
    $slug = legion_coach_normalize_slug($slug);
    if ($slug === LEGION_PILOT_DEMO_SLUG) {
        return dirname(__DIR__) . '/images/pilot-athletes';
    }
    return dirname(__DIR__) . '/images/coach-athletes/' . $slug;
}

function legion_coach_photo_storage_basename($slug, $name) {
    $slug = legion_coach_normalize_slug($slug);
    $norm = legion_normalize_person_name($name);
    if ($slug === LEGION_PILOT_DEMO_SLUG) {
        return 'pilot_' . substr(sha1($norm), 0, 20);
    }
    return $slug . '_' . substr(sha1($norm), 0, 16);
}

function legion_coach_athlete_has_uploaded_photo($slug, $photo) {
    $photo = trim((string) $photo);
    if ($photo === '') {
        return false;
    }
    $prefix = '/images/coach-athletes/' . legion_coach_normalize_slug($slug) . '/';
    if ($slug === LEGION_PILOT_DEMO_SLUG) {
        return strpos($photo, '/images/pilot-athletes/') === 0;
    }
    return strpos($photo, $prefix) === 0;
}
