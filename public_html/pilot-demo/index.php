<?php
require_once dirname(__DIR__) . '/legion-version.php';
$meta = array(
    'slug' => 'pilot-demo',
    'name' => 'Пилотная группа',
    'tagline' => 'Тестовый режим — не отображается в общем списке тренеров',
);
$legionVer = legion_asset_version();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars($meta['name']); ?> — пилот</title>
    <link rel="icon" href="/images/legion-logo.png">
    <?php require dirname(__DIR__) . '/legion-head-fonts.php'; ?>
    <link rel="stylesheet" href="/css/legion.css?v=<?php echo (int)$legionVer; ?>">
    <link rel="stylesheet" href="/css/pilot.css?v=<?php echo (int)$legionVer; ?>">
</head>
<body class="pilot-page">
    <header class="site-header no-print">
        <img src="/images/legion-logo.png" alt="Легион Самара" class="site-logo">
        <div class="site-header-text">
            <span class="pilot-badge">Пилот · не в общем списке</span>
            <h1><?php echo htmlspecialchars($meta['name']); ?></h1>
            <p class="site-tagline"><?php echo htmlspecialchars($meta['tagline']); ?></p>
        </div>
    </header>

    <nav class="legion-site-nav no-print" aria-label="Пилотная группа">
        <span class="legion-site-nav__link is-active" aria-current="page">Рейтинг группы</span>
        <a href="/pilot-demo/training.php" class="legion-site-nav__link">Режим тренировки</a>
        <a href="/" class="legion-site-nav__link">Общий рейтинг</a>
    </nav>

    <?php require dirname(__DIR__) . '/search-bar.php'; ?>

    <p class="pilot-updated no-print" id="pilot-updated"></p>

    <div id="content">
        <p class="note">Загрузка рейтинга…</p>
    </div>

    <?php require __DIR__ . '/pilot-modals.php'; ?>

    <script src="/js/legion-config.js?v=<?php echo (int)$legionVer; ?>" defer></script>
    <script src="/js/legion-core.js?v=<?php echo (int)$legionVer; ?>" defer></script>
    <script src="/js/legion-ui.js?v=<?php echo (int)$legionVer; ?>" defer></script>
    <script src="/js/legion-pilot-photos.js?v=<?php echo (int)$legionVer; ?>" defer></script>
    <script src="/js/legion-pilot-achievements.js?v=<?php echo (int)$legionVer; ?>" defer></script>
    <script src="/js/legion-pilot-micro.js?v=<?php echo (int)$legionVer; ?>" defer></script>
    <script src="/js/legion-pilot-rating.js?v=<?php echo (int)$legionVer; ?>" defer></script>
</body>
</html>
