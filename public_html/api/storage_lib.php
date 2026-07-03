<?php

require_once __DIR__ . '/coaches.php';

function storage_valid_scopes($includeGlobal = true) {
    return legion_valid_storage_scopes($includeGlobal);
}

function storage_validate_scope($scope, $includeGlobal = true) {
    return in_array($scope, storage_valid_scopes($includeGlobal), true) ? $scope : null;
}

/**
 * Объединяет снимки из global и страниц тренеров (для сравнения при пустом global).
 */
function storage_merge_last_results(array $all) {
    $merged = array();
    if (!empty($all['global']) && is_array($all['global'])) {
        foreach ($all['global'] as $name => $marks) {
            if (is_array($marks)) {
                $merged[$name] = $marks;
            }
        }
    }
    foreach ($all as $scopeKey => $scopeData) {
        if ($scopeKey === 'global' || !is_array($scopeData)) {
            continue;
        }
        foreach ($scopeData as $name => $marks) {
            if (!isset($merged[$name]) && is_array($marks)) {
                $merged[$name] = $marks;
            }
        }
    }
    return $merged;
}

function storage_read_json($path, $default = []) {
    if (!file_exists($path)) {
        return $default;
    }

    $fp = fopen($path, 'r');
    if ($fp === false) {
        return $default;
    }

    flock($fp, LOCK_SH);
    $content = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    $data = json_decode($content, true);
    return is_array($data) ? $data : $default;
}

function storage_write_json($path, $data) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }

    $fp = fopen($path, 'c+');
    if ($fp === false) {
        return false;
    }

    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    rewind($fp);
    $written = fwrite($fp, $json);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return $written !== false;
}
