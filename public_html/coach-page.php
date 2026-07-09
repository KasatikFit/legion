<?php
require_once __DIR__ . '/api/coaches.php';
require_once __DIR__ . '/legion-version.php';
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
$legionVer = legion_asset_version();
$coachesJson = json_encode(legion_coaches_for_js(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="icon" href="/images/legion-logo.png">
    <?php require __DIR__ . '/legion-head-fonts.php'; ?>
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
        <a href="/rating-info/?from=<?php echo htmlspecialchars($currentSlug); ?>" class="nav-tab">О системе рейтинга</a>
        <a href="/<?php echo htmlspecialchars($currentSlug); ?>/training.php" class="nav-tab">Режим тренировки</a>
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
    <script src="/js/legion-config.js?v=<?php echo (int)$legionVer; ?>" defer onerror="__legionError('Не найден js/legion-config.js — залейте на сервер')"></script>
    <script src="/js/legion-core.js?v=<?php echo (int)$legionVer; ?>" defer onerror="__legionError('Не найден js/legion-core.js — залейте на сервер')"></script>
    <script src="/js/legion-ui.js?v=<?php echo (int)$legionVer; ?>" defer onerror="__legionError('Не найден js/legion-ui.js — залейте на сервер')"></script>
    <script src="/js/legion-coach.js?v=<?php echo (int)$legionVer; ?>" defer onerror="__legionError('Не найден js/legion-coach.js — залейте на сервер')"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
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
    });
    </script>

    <div class="rotation-panel no-print" id="rotation-panel">
        <div class="rotation-panel-card">
            <div class="rotation-panel-accent" aria-hidden="true"></div>
            <div class="rotation-panel-content">
                <div class="rotation-panel-icon" aria-hidden="true">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 4V2M12 22V20M4 12H2M22 12H20M5.64 5.64L4.22 4.22M19.78 19.78L18.36 18.36M5.64 18.36L4.22 19.78M19.78 4.22L18.36 5.64" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                        <path d="M12 16C14.2091 16 16 14.2091 16 12C16 9.79086 14.2091 8 12 8C9.79086 8 8 9.79086 8 12C8 14.2091 9.79086 16 12 16Z" stroke="currentColor" stroke-width="1.75"/>
                        <path d="M12 6C8.68629 6 6 8.68629 6 12" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="rotation-panel-text">
                    <h3 class="rotation-panel-title"><?php echo htmlspecialchars($rotationTitle); ?></h3>
                    <p class="rotation-panel-hint"><?php echo htmlspecialchars($rotationHint); ?></p>
                    <div id="rotation-status" class="rotation-status" aria-live="polite">
                        <p class="rotation-status-line">Загрузка данных о ротации…</p>
                    </div>
                </div>
                <div class="rotation-panel-actions">
                    <label class="rotation-password-field">
                        <span class="rotation-password-label">Пароль</span>
                        <input type="password" id="rotation-password" class="rotation-password-input" autocomplete="off" placeholder="Для ручной ротации">
                    </label>
                    <button type="button" class="rotation-btn" id="rotation-btn" onclick="rotateLeagues()">
                        <span class="rotation-btn-spinner" aria-hidden="true"></span>
                        <span class="rotation-btn-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M20 12C20 16.4183 16.4183 20 12 20C8.31447 20 5.21994 17.2091 4.27146 13.75M4 12C4 7.58172 7.58172 4 12 4C15.6855 4 18.7801 6.79086 19.7285 10.25" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M19 4V10H13M5 20V14H11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span class="rotation-btn-label">Провести ротацию</span>
                    </button>
                </div>
            </div>
        </div>
        <div id="rotation-log" class="rotation-log" hidden></div>
    </div>
</body>
</html>
