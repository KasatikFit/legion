<?php

require_once __DIR__ . '/diagnostics_lib.php';

define('LEGION_SHEETS_CACHE_DIR', __DIR__ . '/cache/sheets');
define('LEGION_SHEETS_CACHE_TTL', 90);
define('LEGION_SHEETS_CACHE_STALE_MAX', 600);

function legion_sheets_cache_ensure_dir() {
    if (!is_dir(LEGION_SHEETS_CACHE_DIR)) {
        @mkdir(LEGION_SHEETS_CACHE_DIR, 0755, true);
    }
}

function legion_sheets_cache_file($url) {
    return LEGION_SHEETS_CACHE_DIR . '/' . sha1($url) . '.json';
}

/**
 * @return array{ok:bool,body?:string,age?:int,stale?:bool}|null
 */
function legion_sheets_cache_read($url) {
    $file = legion_sheets_cache_file($url);
    if (!file_exists($file)) {
        return null;
    }
    $raw = @file_get_contents($file);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    if (!$data || !isset($data['body'], $data['time'])) {
        return null;
    }
    $age = time() - (int) $data['time'];
    return array(
        'ok' => true,
        'body' => (string) $data['body'],
        'age' => $age,
        'stale' => $age >= LEGION_SHEETS_CACHE_TTL,
    );
}

function legion_sheets_cache_write($url, $body) {
    legion_sheets_cache_ensure_dir();
    $file = legion_sheets_cache_file($url);
    $payload = json_encode(array(
        'time' => time(),
        'body' => $body,
    ), JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return false;
    }
    return @file_put_contents($file, $payload, LOCK_EX) !== false;
}

/**
 * Загрузка одной таблицы с кэшем (свежий → кэш, иначе Google, при ошибке — устаревший кэш).
 */
function legion_fetch_sheet_cached($url, $ttl = null) {
    $ttl = $ttl !== null ? (int) $ttl : LEGION_SHEETS_CACHE_TTL;
    if ($url === '') {
        return array('ok' => false, 'error' => 'Пустой URL');
    }

    $cached = legion_sheets_cache_read($url);
    if ($cached !== null && $cached['age'] < $ttl) {
        return array('ok' => true, 'body' => $cached['body'], 'fromCache' => true);
    }

    $fetch = legion_diagnostics_fetch_url($url);
    if ($fetch['ok']) {
        legion_sheets_cache_write($url, $fetch['body']);
        return array('ok' => true, 'body' => $fetch['body'], 'fromCache' => false);
    }

    if ($cached !== null && $cached['age'] < LEGION_SHEETS_CACHE_STALE_MAX) {
        return array('ok' => true, 'body' => $cached['body'], 'fromCache' => true, 'stale' => true);
    }

    return array('ok' => false, 'error' => isset($fetch['error']) ? $fetch['error'] : 'Не удалось загрузить');
}

/**
 * Параллельная загрузка нескольких таблиц (curl_multi + кэш).
 *
 * @param string[] $urls
 * @return array<string, array{ok:bool,body?:string,error?:string}>
 */
function legion_fetch_sheets_parallel(array $urls, $ttl = null, $useCache = true) {
    $ttl = $ttl !== null ? (int) $ttl : LEGION_SHEETS_CACHE_TTL;
    $results = array();
    $pending = array();

    foreach ($urls as $url) {
        if ($url === '') {
            continue;
        }
        if ($useCache) {
            $cached = legion_sheets_cache_read($url);
            if ($cached !== null && $cached['age'] < $ttl) {
                $results[$url] = array('ok' => true, 'body' => $cached['body'], 'fromCache' => true);
                continue;
            }
            $pending[$url] = $cached;
        } else {
            $pending[$url] = null;
        }
    }

    if (!empty($pending) && function_exists('curl_multi_init')) {
        $mh = curl_multi_init();
        $handles = array();

        foreach (array_keys($pending) as $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 12,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => true,
            ));
            curl_multi_add_handle($mh, $ch);
            $handles[(int) $ch] = array('ch' => $ch, 'url' => $url);
        }

        $running = null;
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running > 0) {
                curl_multi_select($mh, 1.0);
            }
        } while ($running > 0 && $status === CURLM_OK);

        foreach ($handles as $item) {
            $ch = $item['ch'];
            $url = $item['url'];
            $body = curl_multi_getcontent($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            if ($body !== false && $httpCode >= 200 && $httpCode < 300) {
                if ($useCache) {
                    legion_sheets_cache_write($url, $body);
                }
                $results[$url] = array('ok' => true, 'body' => $body, 'fromCache' => false);
            } elseif ($useCache && isset($pending[$url]) && $pending[$url] !== null && $pending[$url]['age'] < LEGION_SHEETS_CACHE_STALE_MAX) {
                $results[$url] = array('ok' => true, 'body' => $pending[$url]['body'], 'fromCache' => true, 'stale' => true);
            } else {
                $results[$url] = array('ok' => false, 'error' => $err !== '' ? $err : 'HTTP ' . $httpCode);
            }
        }

        curl_multi_close($mh);
    } elseif (!empty($pending)) {
        foreach (array_keys($pending) as $url) {
            if ($useCache) {
                $results[$url] = legion_fetch_sheet_cached($url, $ttl);
            } else {
                $fetch = legion_diagnostics_fetch_url($url);
                $results[$url] = $fetch['ok']
                    ? array('ok' => true, 'body' => $fetch['body'], 'fromCache' => false)
                    : array('ok' => false, 'error' => isset($fetch['error']) ? $fetch['error'] : 'Не удалось загрузить');
            }
        }
    }

    return $results;
}
