<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не поддерживается']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['entries']) || !is_array($data['entries'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверный формат данных']);
    exit;
}

// Ограничение: последние N записей на каждого спортсмена
define('HISTORY_PER_ATHLETE', 50);

function trim_history_per_athlete(array $entries, int $limit = HISTORY_PER_ATHLETE): array {
    $indicesByName = [];
    foreach ($entries as $i => $entry) {
        $name = isset($entry['name']) ? $entry['name'] : '';
        if (!isset($indicesByName[$name])) {
            $indicesByName[$name] = [];
        }
        $indicesByName[$name][] = $i;
    }

    $keep = [];
    foreach ($indicesByName as $indices) {
        foreach (array_slice($indices, -$limit) as $i) {
            $keep[$i] = true;
        }
    }

    $trimmed = [];
    foreach ($entries as $i => $entry) {
        if (isset($keep[$i])) {
            $trimmed[] = $entry;
        }
    }
    return $trimmed;
}

$historyFile = __DIR__ . '/history.json';

// Читаем существующую историю
$existing = [];
if (file_exists($historyFile)) {
    $content = file_get_contents($historyFile);
    $existing = json_decode($content, true) ?: [];
}

// Добавляем новые записи
$existing = array_merge($existing, $data['entries']);

// Оставляем последние N записей на каждого спортсмена
$existing = trim_history_per_athlete($existing);

// Сохраняем
file_put_contents($historyFile, json_encode($existing, JSON_UNESCAPED_UNICODE));

echo json_encode(['success' => true, 'count' => count($data['entries'])]);