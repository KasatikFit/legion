<?php
require_once __DIR__ . '/../api/coaches.php';
require_once __DIR__ . '/../legion-version.php';

$coachSlug = isset($_GET['coach']) ? trim((string) $_GET['coach']) : '';
$athleteName = isset($_GET['name']) ? trim((string) $_GET['name']) : '';
$athleteId = 0;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $athleteId = (int) $_GET['id'];
} elseif (isset($_GET['athleteId']) && is_numeric($_GET['athleteId'])) {
    $athleteId = (int) $_GET['athleteId'];
}
$coaches = legion_coaches_config();
$legionVer = legion_asset_version();

if ($athleteId <= 0 && $athleteName === '') {
    http_response_code(404);
    echo 'Спортсмен не указан';
    exit;
}

$pageTitle = $athleteName !== ''
    ? ($athleteName . ' — Легион Силы')
    : 'Карточка спортсмена — Легион Силы';
$legionNavActive = $coachSlug !== '' && isset($coaches[$coachSlug]) ? 'coach' : 'club';
$legionNavCoachSlug = $coachSlug;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="icon" href="/images/legion-logo.png">
    <?php require __DIR__ . '/../legion-head-fonts.php'; ?>
    <link rel="stylesheet" href="/css/legion.css?v=<?php echo (int) $legionVer; ?>">
</head>
<body data-legion-page="athlete"<?php
echo $athleteId > 0 ? ' data-athlete-id="' . (int) $athleteId . '"' : '';
echo $athleteName !== '' ? ' data-athlete-name="' . htmlspecialchars($athleteName, ENT_QUOTES, 'UTF-8') . '"' : '';
echo $coachSlug !== '' ? ' data-coach-slug="' . htmlspecialchars($coachSlug, ENT_QUOTES, 'UTF-8') . '"' : '';
?>>
    <header class="site-header no-print">
        <img src="/images/legion-logo.png" alt="Легион Самара" class="site-logo">
        <div class="site-header-text">
            <h1>Карточка спортсмена</h1>
            <p class="site-tagline">Сила. Дисциплина. Результат.</p>
        </div>
    </header>

    <?php require __DIR__ . '/../legion-site-nav.php'; ?>

    <main class="athlete-profile-page no-print" id="athlete-profile-root">
        <p class="note">Загрузка карточки спортсмена…</p>
    </main>

    <div class="modal-overlay" id="rankModal" onclick="closeRankModal(event)">
        <div class="modal modal--rank" onclick="event.stopPropagation()">
            <button class="modal-close" onclick="closeRankModal()">✖</button>
            <h2 id="rank-modal-title" class="rank-modal-title"></h2>
            <div id="rank-modal-content" class="rank-modal-body"></div>
        </div>
    </div>

    <?php require __DIR__ . '/../scripts-legion.php'; ?>
    <script src="/js/legion-athlete.js?v=<?php echo (int) $legionVer; ?>" defer></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('athlete-profile-root');
        function showError(msg) {
            if (root) root.innerHTML = '<p class="error">' + msg + '</p>';
        }
        if (typeof LegionAthletePage === 'undefined') {
            showError('Не загружен legion-athlete.js — залейте файл на сервер.');
            return;
        }
        try {
            LegionAthletePage.init();
        } catch (e) {
            showError('Ошибка: ' + (e.message || e));
        }
    });
    </script>
</body>
</html>
