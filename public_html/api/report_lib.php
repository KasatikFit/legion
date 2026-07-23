<?php

require_once __DIR__ . '/pilot_lib.php';
require_once __DIR__ . '/coaches.php';

function legion_report_token_config_path() {
    return __DIR__ . '/report_token_config.php';
}

function legion_report_is_placeholder_token($token) {
    $token = legion_report_normalize_token($token);
    return $token === ''
        || $token === 'YOUR_SECRET_TOKEN'
        || $token === 'замените-на-длинный-случайный-токен';
}

function legion_report_sanitize_token_entry($value) {
    $normalized = legion_report_normalize_token($value);
    return legion_report_is_placeholder_token($normalized) ? '' : $normalized;
}

function legion_report_load_config_from_file() {
    $path = legion_report_token_config_path();
    if (!is_file($path)) {
        return array('status' => 'missing', 'map' => array());
    }

    $loaded = null;
    $previousHandler = set_error_handler(function ($severity, $message) {
        throw new ErrorException($message, 0, $severity);
    });
    try {
        $loaded = require $path;
    } catch (Throwable $e) {
        restore_error_handler();
        return array('status' => 'parse_error', 'map' => array());
    }
    restore_error_handler();

    if (!is_array($loaded)) {
        return array('status' => 'not_array', 'map' => array());
    }

    $map = array();
    foreach ($loaded as $key => $value) {
        $map[(string) $key] = legion_report_sanitize_token_entry($value);
    }

    if ($map === array()) {
        return array('status' => 'empty_array', 'map' => $map);
    }

    return array('status' => 'ok', 'map' => $map);
}

function legion_report_define_fallback_tokens() {
    $map = array();
    if (defined('LEGION_REPORT_TOKEN_GLOBAL')) {
        $map['global'] = legion_report_sanitize_token_entry(LEGION_REPORT_TOKEN_GLOBAL);
    }
    if (defined('LEGION_REPORT_TOKEN')) {
        if (!isset($map['global']) || $map['global'] === '') {
            $map['global'] = legion_report_sanitize_token_entry(LEGION_REPORT_TOKEN);
        }
    }
    return $map;
}

function legion_report_token_map() {
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $loaded = legion_report_load_config_from_file();
    $map = $loaded['map'];

    foreach (legion_report_define_fallback_tokens() as $key => $value) {
        if ($value !== '' && (!isset($map[$key]) || $map[$key] === '')) {
            $map[$key] = $value;
        }
    }

    return $map;
}

function legion_report_config_load_status() {
    $loaded = legion_report_load_config_from_file();
    if ($loaded['status'] !== 'ok' && $loaded['status'] !== 'empty_array') {
        if (legion_report_has_any_token(legion_report_define_fallback_tokens())) {
            return 'define_fallback';
        }
        return $loaded['status'];
    }

    $map = $loaded['map'];
    foreach (legion_report_define_fallback_tokens() as $key => $value) {
        if ($value !== '' && (!isset($map[$key]) || $map[$key] === '')) {
            $map[$key] = $value;
        }
    }

    if ($loaded['status'] === 'empty_array' && $map === array()) {
        return 'empty_array';
    }
    if (!legion_report_has_any_token($map)) {
        return 'no_tokens';
    }
    return 'ok';
}

function legion_report_has_any_token($map = null) {
    if ($map === null) {
        $map = legion_report_token_map();
    }
    foreach ($map as $value) {
        if ($value !== '') {
            return true;
        }
    }
    return false;
}

function legion_report_configured_keys($map = null) {
    if ($map === null) {
        $map = legion_report_token_map();
    }
    $keys = array();
    foreach ($map as $key => $value) {
        if ($value !== '') {
            $keys[] = (string) $key;
        }
    }
    return $keys;
}

function legion_report_normalize_token($token) {
    $token = (string) $token;
    $token = trim($token);
    $token = preg_replace('/\s+/u', '', $token);
    return $token;
}

function legion_report_config_file_exists() {
    return is_file(legion_report_token_config_path());
}

function legion_report_token_get($coachSlug) {
    $map = legion_report_token_map();
    if ($coachSlug === 'global') {
        return isset($map['global']) ? (string) $map['global'] : '';
    }
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    return isset($map[$coachSlug]) ? (string) $map[$coachSlug] : '';
}

function legion_report_token_is_configured($coachSlug = LEGION_PILOT_SLUG) {
    if (!legion_report_has_any_token()) {
        return false;
    }
    if (legion_report_token_get('global') !== '') {
        return true;
    }
    return legion_report_token_get($coachSlug) !== '';
}

function legion_report_token_verify($token, $coachSlug = LEGION_PILOT_SLUG) {
    $token = legion_report_normalize_token($token);
    if ($token === '') {
        return false;
    }
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    $expected = legion_report_token_get($coachSlug);
    if ($expected !== '' && hash_equals($expected, $token)) {
        return true;
    }
    $global = legion_report_token_get('global');
    if ($global !== '' && hash_equals($global, $token)) {
        return true;
    }
    return false;
}

function legion_report_coach_is_valid($coachSlug) {
    try {
        $coachSlug = legion_coach_normalize_slug($coachSlug);
    } catch (InvalidArgumentException $e) {
        return false;
    }
    if ($coachSlug === LEGION_PILOT_SLUG) {
        return true;
    }
    $coaches = legion_coaches_registry();
    return isset($coaches[$coachSlug]);
}

function legion_report_resolve_coach_slug($raw, $fallback = '') {
    $raw = trim((string) $raw);
    if ($raw === '') {
        $raw = trim((string) $fallback);
    }
    if ($raw === '') {
        return '';
    }
    try {
        $slug = legion_coach_normalize_slug($raw);
    } catch (InvalidArgumentException $e) {
        return '';
    }
    return legion_report_coach_is_valid($slug) ? $slug : '';
}

function legion_report_all_coach_slugs() {
    $slugs = array_keys(legion_coaches_registry());
    if (!in_array(LEGION_PILOT_SLUG, $slugs, true)) {
        array_unshift($slugs, LEGION_PILOT_SLUG);
    }
    sort($slugs, SORT_STRING);
    return $slugs;
}

function legion_report_forbidden_html(array $hints = array()) {
    $items = '';
    foreach ($hints as $hint) {
        $items .= '<li>' . htmlspecialchars((string) $hint, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    $list = $items !== '' ? '<ul>' . $items . '</ul>' : '';
    return '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8">'
        . '<meta name="robots" content="noindex,nofollow"><title>403</title>'
        . '<style>body{font-family:system-ui,sans-serif;max-width:640px;margin:40px auto;padding:0 16px;line-height:1.5}'
        . 'ul{padding-left:20px}code{background:#f3f3f3;padding:2px 6px;border-radius:4px}</style></head><body>'
        . '<h1>Доступ запрещён</h1>'
        . '<p>Ссылка на отчёт не прошла проверку токена.</p>'
        . $list
        . '<p><small>Токен в текстовом файле на компьютере не считается — он должен быть в '
        . '<code>api/report_token_config.php</code> на сервере.</small></p>'
        . '</body></html>';
}

function legion_report_auth_failure_hints($token, $coachSlug, $defaultCoach = '') {
    $hints = array();
    $status = legion_report_config_load_status();
    $map = legion_report_token_map();
    $configuredKeys = legion_report_configured_keys($map);

    if ($status === 'missing') {
        $hints[] = 'На сервере нет файла api/report_token_config.php. Скопируйте api/report_token_config.example.php → api/report_token_config.php через WinSCP.';
        return $hints;
    }
    if ($status === 'parse_error') {
        $hints[] = 'В api/report_token_config.php синтаксическая ошибка PHP — файл не выполняется.';
        $hints[] = 'Проверьте, что токен в одинарных кавычках: \'global\' => \'ваш-токен\'. В двойных кавычках символ $ в токене ломает значение.';
        return $hints;
    }
    if ($status === 'not_array') {
        $hints[] = 'Файл api/report_token_config.php должен заканчиваться на return array(...);';
        $hints[] = 'Либо задайте define(\'LEGION_REPORT_TOKEN_GLOBAL\', \'ваш-токен\'); и return array();';
        return $hints;
    }
    if ($status === 'empty_array' || $status === 'no_tokens') {
        $hints[] = 'В report_token_config.php нет ни одного активного токена.';
        $hints[] = 'Строка с токеном не должна быть закомментирована (без // в начале).';
        $hints[] = 'Минимум: return array(\'global\' => \'ваш-токен\');';
        $hints[] = 'Токен с символом $ пишите только в одинарных кавычках \'...\'';
        return $hints;
    }

    if ($coachSlug === '') {
        $hints[] = 'В ссылке не указана группа: добавьте &coach=slug (например coach=yakutin).';
        $hints[] = 'Либо откройте /report.php?token=... с ключом global в конфиге — появится выбор группы.';
        if ($defaultCoach !== '') {
            $hints[] = 'Для пилота: /pilot-demo/report.php?token=...&id=...';
        }
        if (!empty($configuredKeys) && !in_array('global', $configuredKeys, true)) {
            $hints[] = 'Сейчас в конфиге только: ' . implode(', ', $configuredKeys) . '. Для всех групп добавьте ключ global.';
        }
        return $hints;
    }

    $expected = legion_report_token_get($coachSlug);
    $global = legion_report_token_get('global');
    if (!empty($configuredKeys)) {
        $hints[] = 'В конфиге заданы ключи: ' . implode(', ', $configuredKeys) . '.';
    }
    if ($expected === '' && $global === '') {
        $hints[] = 'Для coach=' . $coachSlug . ' нужен ключ «' . $coachSlug . '» или «global» в report_token_config.php.';
    } elseif ($token === '') {
        $hints[] = 'В ссылке отсутствует параметр token=...';
    } else {
        $hints[] = 'Токен в ссылке не совпадает с report_token_config.php для группы «' . $coachSlug . '».';
        $hints[] = 'Сверьте токен в ссылке и в PHP-файле (без пробелов). Используйте одинарные кавычки, если в токене есть $.';
    }
    $hints[] = 'Пример: /report.php?token=ВАШ_ТОКЕН&coach=' . $coachSlug . '&id=ID';
    return $hints;
}

function legion_report_not_found_html($message) {
    $message = htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8');
    return '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="robots" content="noindex,nofollow"><title>404</title></head><body><p>' . $message . '</p></body></html>';
}

function legion_report_send_no_cache_headers() {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function legion_report_wants_ai() {
    if (isset($_GET['ai']) && (string) $_GET['ai'] === '0') {
        return false;
    }
    if (!empty($_GET['ai'])) {
        return true;
    }
    require_once __DIR__ . '/yandex_gpt_lib.php';
    return legion_yandex_gpt_is_configured();
}

function legion_report_build_url($reportBase, $token, $coachSlug, $name = '', $withAi = false, $athleteId = 0) {
    $parts = array(
        'token' => $token,
        'coach' => $coachSlug,
    );
    $athleteId = (int) $athleteId;
    if ($athleteId > 0) {
        $parts['id'] = $athleteId;
    } elseif ($name !== '') {
        $parts['name'] = $name;
    }
    if ($withAi) {
        $parts['ai'] = '1';
    }
    $qs = http_build_query($parts, '', '&', PHP_QUERY_RFC3986);
    return rtrim((string) $reportBase, '?') . '?' . $qs;
}

function legion_report_render_coach_picker_html($token, $reportBase, array $options = array()) {
    require_once __DIR__ . '/athlete_dossier_lib.php';
    $cssVer = isset($options['cssVersion']) ? (int) $options['cssVersion'] : 1;
    $tokenQ = rawurlencode($token);
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Отчёты — выбор группы</title>
    <link rel="stylesheet" href="/css/athlete-report.css?v=<?php echo (int) $cssVer; ?>">
</head>
<body class="athlete-report">
    <article class="report-sheet report-picker">
        <header class="report-header">
            <div class="report-brand">
                <p class="report-brand-name">Легион Самара</p>
                <p class="report-brand-sub">Закрытые отчёты</p>
            </div>
        </header>
        <section class="report-section">
            <h1>Выберите группу</h1>
            <p class="muted">Далее откроется список спортсменов выбранной группы.</p>
            <ul class="report-picker-list">
                <?php foreach (legion_report_all_coach_slugs() as $slug) :
                    $meta = legion_coach_meta($slug);
                    $href = legion_report_h($reportBase . '?token=' . $tokenQ . '&coach=' . rawurlencode($slug));
                    ?>
                <li><a href="<?php echo $href; ?>"><?php echo legion_report_h($meta['name']); ?></a> <span class="muted">(<?php echo legion_report_h($slug); ?>)</span></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </article>
</body>
</html>
    <?php
    return ob_get_clean();
}

function legion_report_serve_request(array $options = array()) {
    require_once __DIR__ . '/athlete_dossier_lib.php';
    require_once __DIR__ . '/yandex_gpt_lib.php';

    $defaultCoach = isset($options['defaultCoachSlug']) ? (string) $options['defaultCoachSlug'] : '';
    $reportBase = isset($options['reportBasePath']) ? (string) $options['reportBasePath'] : '/report.php';
    $omitCoachInUrl = !empty($options['omitCoachInUrl']);
    $cssVer = isset($options['cssVersion']) ? (int) $options['cssVersion'] : 1;

    $token = legion_report_normalize_token(isset($_GET['token']) ? $_GET['token'] : '');
    $name = isset($_GET['name']) ? trim((string) $_GET['name']) : '';
    $athleteId = 0;
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $athleteId = (int) $_GET['id'];
    } elseif (isset($_GET['athleteId']) && is_numeric($_GET['athleteId'])) {
        $athleteId = (int) $_GET['athleteId'];
    }
    $withAi = legion_report_wants_ai();
    $coachSlug = legion_report_resolve_coach_slug(
        isset($_GET['coach']) ? $_GET['coach'] : '',
        $defaultCoach
    );

    header('X-Robots-Tag: noindex, nofollow', true);
    legion_report_send_no_cache_headers();

    if ($coachSlug === '') {
        if (legion_report_token_get('global') !== '' && legion_report_token_verify($token, LEGION_PILOT_SLUG)) {
            header('Content-Type: text/html; charset=utf-8');
            echo legion_report_render_coach_picker_html($token, $reportBase, array(
                'cssVersion' => $cssVer,
            ));
            return;
        }
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo legion_report_forbidden_html(legion_report_auth_failure_hints($token, '', $defaultCoach));
        return;
    }

    if (!legion_report_token_verify($token, $coachSlug)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo legion_report_forbidden_html(legion_report_auth_failure_hints($token, $coachSlug, $defaultCoach));
        return;
    }

    if ($athleteId <= 0 && $name === '') {
        $data = legion_pilot_load_dataset($coachSlug);
        $athletes = isset($data['athletes']) && is_array($data['athletes']) ? $data['athletes'] : array();
        header('Content-Type: text/html; charset=utf-8');
        echo legion_dossier_render_picker_html($coachSlug, $token, $athletes, array(
            'cssVersion' => $cssVer,
            'reportBase' => $reportBase,
            'omitCoachInUrl' => $omitCoachInUrl,
        ));
        return;
    }

    $dossier = legion_dossier_build($coachSlug, $name, $athleteId);
    if ($dossier === null) {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo legion_report_not_found_html('Спортсмен не найден в этой группе.');
        return;
    }

    $aiText = null;
    $aiError = '';
    if ($withAi) {
        try {
            if (!legion_yandex_gpt_is_configured()) {
                $aiError = 'YandexGPT не настроен. Скопируйте api/yandex_gpt_config.example.php → api/yandex_gpt_config.php на сервере.';
            } else {
                $aiText = legion_yandex_gpt_dossier_recommendations($dossier);
            }
        } catch (Exception $e) {
            $aiError = $e->getMessage();
        }
    }

    header('Content-Type: text/html; charset=utf-8');
    echo legion_dossier_render_html($dossier, $aiText, array(
        'cssVersion' => $cssVer,
        'withAi' => $withAi,
        'aiError' => $aiError,
        'aiAuto' => $withAi && !isset($_GET['ai']),
    ));
}
