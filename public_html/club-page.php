<?php
require_once __DIR__ . '/api/coaches.php';
$coaches = legion_coaches_config();
$LEGION_PAGE = 'club';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Общий рейтинг Легиона Силы</title>
    <link rel="icon" href="/images/legion-logo.png">
    <link rel="stylesheet" href="/css/legion.css?v=20">
</head>
<body data-legion-page="club">
    <header class="site-header no-print">
        <img src="/images/legion-logo.png" alt="Легион Самара" class="site-logo">
        <div class="site-header-text">
            <h1>Легион Силы — Общий рейтинг</h1>
            <p class="site-tagline">Сила. Дисциплина. Результат.</p>
        </div>
    </header>

    <div id="club-stats" class="club-stats no-print"></div>

    <div class="navbar no-print">
        <span class="nav-tab active-tab">Общий рейтинг</span>
        <div class="dropdown">
            <button type="button" class="dropbtn" aria-haspopup="true" aria-expanded="false">Тренеры ▼</button>
            <div class="dropdown-content">
                <?php foreach ($coaches as $slug => $coach): ?>
                <a href="/<?php echo htmlspecialchars($slug); ?>/"><?php echo legion_coach_nav_icon(); ?> <?php echo htmlspecialchars($coach['name']); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <a href="/rating-info/" class="nav-tab">О системе рейтинга</a>
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
        <div class="tab" onclick="switchTab('hall')">🏆 Зал славы</div>
    </div>

    <div id="content">
        <p class="note">Загрузка рейтинга…</p>
    </div>

    <?php require __DIR__ . '/modals-club.php'; ?>
    <?php require __DIR__ . '/scripts-legion.php'; ?>
    <script src="/js/legion-club.js?v=20"></script>
    <script>
    (function () {
        var content = document.getElementById('content');
        function showError(msg) {
            if (content) content.innerHTML = '<p class="error">' + msg + '</p>';
        }
        if (typeof LegionClubPage === 'undefined') {
            showError('Не загружен legion-club.js — залейте js/legion-club.js на сервер.');
            return;
        }
        try {
            if (typeof LegionClubPage.boot === 'function') LegionClubPage.boot();
            else LegionClubPage.init();
        } catch (e) {
            showError('Ошибка: ' + (e.message || e));
        }
    })();
    </script>
</body>
</html>
