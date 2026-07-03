<?php
require_once __DIR__ . '/api/coaches.php';
$coaches = legion_coaches_config();
$currentSlug = isset($LEGION_COACH_SLUG) ? $LEGION_COACH_SLUG : (isset($GLOBALS['LEGION_COACH_SLUG']) ? $GLOBALS['LEGION_COACH_SLUG'] : legion_coach_slug_from_script());
$currentCoach = isset($coaches[$currentSlug]) ? $coaches[$currentSlug] : null;
if (!$currentCoach) {
    http_response_code(404);
    echo 'Тренер не найден';
    exit;
}
$pageTitle = 'Рейтинг — ' . $currentCoach['name'];
$rotationTitle = 'Ротация элиты';
$rotationHint = 'Элита группы обновляется автоматически в начале месяца. Ручная ротация — по паролю.';
$LEGION_PAGE = 'coach';
$legionVer = 19;
$coachesJson = json_encode(legion_coaches_for_js(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="icon" href="/images/legion-logo.png">
    <link rel="stylesheet" href="/css/legion.css?v=<?php echo (int)$legionVer; ?>">
</head>
<body data-legion-page="coach" data-coach-slug="<?php echo htmlspecialchars($currentSlug); ?>">
    <header class="site-header no-print">
        <img src="/images/legion-logo.png" alt="Легион Самара" class="site-logo">
        <div class="site-header-text">
            <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
            <p class="site-tagline"><?php echo htmlspecialchars($currentCoach['tagline']); ?></p>
        </div>
    </header>

    <div class="navbar no-print">
        <a href="/" class="nav-tab">Общий рейтинг</a>
        <div class="dropdown">
            <button type="button" class="dropbtn" aria-haspopup="true" aria-expanded="false">Тренеры ▼</button>
            <div class="dropdown-content">
                <?php foreach ($coaches as $slug => $coach): ?>
                <a href="/<?php echo htmlspecialchars($slug); ?>/"<?php echo $slug === $currentSlug ? ' style="font-weight:bold; background:rgba(255,255,255,0.1);"' : ''; ?>>
                    <?php echo legion_coach_nav_icon(); ?> <?php echo htmlspecialchars($coach['name']); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <span class="nav-tab clickable" onclick="showRatingInfo()">О системе рейтинга</span>
    </div>

    <?php require __DIR__ . '/search-bar.php'; ?>

    <div class="tabs no-print">
        <div class="tab active" onclick="switchTab('overall')">Общий рейтинг</div>
        <div class="tab" onclick="switchTab('push')">Отжимания</div>
        <div class="tab" onclick="switchTab('pull')">Подтягивания</div>
        <div class="tab" onclick="switchTab('hang')">Вис (сек)</div>
        <div class="tab" onclick="switchTab('burpee')">Бёрпи за 1 мин</div>
        <div class="tab" onclick="switchTab('crunch')">Скручивания</div>
        <div class="tab" onclick="switchTab('jump')">Прыжок в длину (см)</div>
    </div>

    <div id="content">
        <p class="note">Загрузка рейтинга…</p>
        <noscript><p class="error">Для работы рейтинга нужен JavaScript.</p></noscript>
    </div>

    <?php require __DIR__ . '/modals-coach.php'; ?>

    <script>
    window.__legionStatus = function (msg) {
        var c = document.getElementById('content');
        if (c) c.innerHTML = '<p class="note">' + msg + '</p>';
    };
    window.__legionError = function (msg) {
        var c = document.getElementById('content');
        if (c) c.innerHTML = '<p class="error">' + msg + '</p>';
    };
    __legionStatus('Запуск страницы…');
    </script>
    <script>window.LegionCoachesFromServer = <?php echo $coachesJson; ?>;</script>
    <script src="/js/legion-config.js?v=<?php echo (int)$legionVer; ?>" onerror="__legionError('Не найден js/legion-config.js — залейте на сервер')"></script>
    <script src="/js/legion-core.js?v=<?php echo (int)$legionVer; ?>" onerror="__legionError('Не найден js/legion-core.js — залейте на сервер')"></script>
    <script src="/js/legion-ui.js?v=<?php echo (int)$legionVer; ?>" onerror="__legionError('Не найден js/legion-ui.js — залейте на сервер')"></script>
    <script src="/js/legion-coach.js?v=<?php echo (int)$legionVer; ?>" onerror="__legionError('Не найден js/legion-coach.js — залейте на сервер')"></script>
    <script>
    (function () {
        if (typeof LegionConfig === 'undefined') {
            __legionError('legion-config.js не загрузился');
            return;
        }
        if (typeof LegionCore === 'undefined') {
            __legionError('legion-core.js не загрузился');
            return;
        }
        if (typeof LegionCoachPage === 'undefined') {
            __legionError('legion-coach.js не загрузился');
            return;
        }
        try {
            if (typeof LegionCoachPage.boot === 'function') {
                LegionCoachPage.boot();
            } else {
                LegionCoachPage.init();
            }
        } catch (e) {
            __legionError('Ошибка: ' + (e.message || e));
        }
    })();
    </script>

    <?php require __DIR__ . '/rotation-bar.php'; ?>
</body>
</html>
