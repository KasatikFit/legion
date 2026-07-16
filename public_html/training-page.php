<?php
require_once __DIR__ . '/api/coaches.php';
require_once __DIR__ . '/api/coach_auth_lib.php';
require_once __DIR__ . '/legion-version.php';

$coaches = legion_coaches_config();
$currentSlug = isset($LEGION_COACH_SLUG) ? $LEGION_COACH_SLUG : (isset($GLOBALS['LEGION_COACH_SLUG']) ? $GLOBALS['LEGION_COACH_SLUG'] : '');
$currentCoach = isset($coaches[$currentSlug]) ? $coaches[$currentSlug] : null;
if (!$currentCoach) {
    http_response_code(404);
    echo 'Тренер не найден';
    exit;
}

$legionVer = legion_asset_version();
$authConfigured = legion_coach_auth_is_configured($currentSlug);
$pageTitle = 'Тренировка — ' . $currentCoach['name'];
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
    <link rel="stylesheet" href="/css/pilot.css?v=<?php echo (int)$legionVer; ?>">
</head>
<body class="pilot-page" data-coach-slug="<?php echo htmlspecialchars($currentSlug); ?>">
    <header class="site-header no-print">
        <img src="/images/legion-logo.png" alt="Легион Самара" class="site-logo">
        <div class="site-header-text">
            <span class="pilot-badge">Режим тренировки</span>
            <h1><?php echo htmlspecialchars($currentCoach['name']); ?></h1>
            <p class="site-tagline">Ввод результатов на тренировке — автосохранение</p>
        </div>
    </header>

    <?php
    $legionNavActive = 'training';
    $legionNavCoachSlug = $currentSlug;
    require __DIR__ . '/legion-site-nav.php';
    ?>

    <div id="pilot-training-root">
        <div class="pilot-login-card" id="pilot-login-static">
            <h2>Вход тренера</h2>
            <p><?php echo htmlspecialchars($currentCoach['name']); ?></p>
            <?php if (!$authConfigured): ?>
                <p class="error">На сервере не настроен пароль для этой группы.<br>
                Скопируйте <code>api/coach_auth.example.php</code> → <code>api/coach_auth.php</code>
                и задайте пароль для slug <code><?php echo htmlspecialchars($currentSlug); ?></code>.</p>
            <?php else: ?>
                <p class="note" style="margin-bottom:12px">Введите пароль режима тренировки для этой группы.</p>
            <?php endif; ?>
            <form id="pilot-login-form">
                <input type="password" id="pilot-login-password" placeholder="Пароль" autocomplete="current-password" required<?php echo $authConfigured ? '' : ' disabled'; ?>>
                <button type="submit" class="pilot-btn pilot-btn--primary" style="width:100%"<?php echo $authConfigured ? '' : ' disabled'; ?>>Войти</button>
            </form>
            <p id="pilot-login-error" class="error" style="margin-top:12px;display:none"></p>
        </div>
    </div>

    <script>
    window.__legionCoachSlug = <?php echo json_encode($currentSlug, JSON_UNESCAPED_UNICODE); ?>;
    window.__pilotAuthConfigured = <?php echo $authConfigured ? 'true' : 'false'; ?>;
    </script>
    <script src="/js/legion-config.js?v=<?php echo (int)$legionVer; ?>" defer></script>
    <script src="/js/legion-core.js?v=<?php echo (int)$legionVer; ?>" defer></script>
    <script src="/js/legion-ui.js?v=<?php echo (int)$legionVer; ?>" defer></script>
    <script src="/js/legion-pilot-photos.js?v=<?php echo (int)$legionVer; ?>" defer></script>
    <script src="/js/legion-pilot-achievements.js?v=<?php echo (int)$legionVer; ?>" defer></script>
    <script src="/js/legion-pilot-training.js?v=<?php echo (int)$legionVer; ?>" defer></script>
</body>
</html>
