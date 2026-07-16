<?php
require_once __DIR__ . '/api/coaches.php';
require_once __DIR__ . '/legion-version.php';
$coaches = legion_coaches_config();
$LEGION_PAGE = 'club';
$legionVer = legion_asset_version();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Общий рейтинг Легиона Силы</title>
    <link rel="icon" href="/images/legion-logo.png">
    <?php require __DIR__ . '/legion-head-fonts.php'; ?>
    <link rel="stylesheet" href="/css/legion.css?v=<?php echo (int)$legionVer; ?>">
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

    <?php
    $legionNavActive = 'club';
    require __DIR__ . '/legion-site-nav.php';
    ?>

    <?php require __DIR__ . '/search-bar.php'; ?>

    <?php
    $legionExerciseTabsIncludeHall = true;
    $legionExerciseTabActive = 'overall';
    require __DIR__ . '/legion-exercise-tabs.php';
    ?>

    <div id="content">
        <p class="note">Загрузка общего рейтинга с сервера…</p>
    </div>

    <?php require __DIR__ . '/modals-club.php'; ?>
    <?php require __DIR__ . '/scripts-legion.php'; ?>
    <script src="/js/legion-club.js?v=<?php echo (int)$legionVer; ?>" defer onerror="(function(){var c=document.getElementById('content');if(c)c.innerHTML='<p class=error>Не найден js/legion-club.js — залейте файл на сервер.</p>';})()"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
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
    });
    </script>
</body>
</html>
