<?php
require_once dirname(__DIR__) . '/legion-version.php';
require_once dirname(__DIR__) . '/api/diagnostics_lib.php';

$legionVer = legion_asset_version();
$report = legion_diagnostics_run();

function legion_diag_status_label($status) {
    if ($status === 'ok') return 'OK';
    if ($status === 'warn') return 'Внимание';
    return 'Ошибка';
}

function legion_diag_status_class($status) {
    if ($status === 'ok') return 'diag-ok';
    if ($status === 'warn') return 'diag-warn';
    return 'diag-error';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Диагностика — Легион Силы</title>
    <link rel="icon" href="/images/legion-logo.png">
    <link rel="stylesheet" href="/css/legion.css?v=<?php echo (int)$legionVer; ?>">
</head>
<body class="guide-page diag-page">
    <header class="site-header no-print">
        <img src="/images/legion-logo.png" alt="Легион Самара" class="site-logo">
        <div class="site-header-text">
            <h1>Диагностика сайта</h1>
            <p class="site-tagline">Проверка файлов, API и Google Таблиц</p>
        </div>
    </header>

    <nav class="navbar no-print guide-nav">
        <a href="/" class="nav-tab">← К рейтингу</a>
        <a href="?refresh=1" class="nav-tab">Обновить проверку</a>
    </nav>

    <main class="guide-main diag-main">
        <section class="diag-summary">
            <p class="diag-meta">Проверено: <strong><?php echo htmlspecialchars($report['checkedAt']); ?></strong> · версия статики <strong>v<?php echo (int)$report['version']; ?></strong></p>
            <div class="diag-summary-cards">
                <div class="diag-summary-card diag-ok">
                    <span class="diag-summary-num"><?php echo (int)$report['summary']['ok']; ?></span>
                    <span class="diag-summary-label">OK</span>
                </div>
                <div class="diag-summary-card diag-warn">
                    <span class="diag-summary-num"><?php echo (int)$report['summary']['warn']; ?></span>
                    <span class="diag-summary-label">Внимание</span>
                </div>
                <div class="diag-summary-card diag-error">
                    <span class="diag-summary-num"><?php echo (int)$report['summary']['error']; ?></span>
                    <span class="diag-summary-label">Ошибки</span>
                </div>
            </div>
            <?php if ($report['summary']['error'] > 0): ?>
            <p class="diag-hint diag-hint--error">Есть критические ошибки — рейтинг может не загружаться или работать частично.</p>
            <?php elseif ($report['summary']['warn'] > 0): ?>
            <p class="diag-hint diag-hint--warn">Есть предупреждения — сайт работает, но стоит исправить.</p>
            <?php else: ?>
            <p class="diag-hint diag-hint--ok">Всё в порядке.</p>
            <?php endif; ?>
        </section>

        <?php foreach ($report['checks'] as $group): ?>
        <section class="diag-section">
            <h2><?php echo htmlspecialchars($group['group']); ?></h2>
            <div class="table-wrap">
                <table class="rating-table diag-table">
                    <thead>
                        <tr>
                            <th>Проверка</th>
                            <th>Статус</th>
                            <th>Детали</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($group['items'] as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><span class="diag-badge <?php echo legion_diag_status_class($item['status']); ?>"><?php echo legion_diag_status_label($item['status']); ?></span></td>
                            <td><?php echo htmlspecialchars($item['detail']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endforeach; ?>

        <section class="diag-section diag-notes">
            <h2>Памятка</h2>
            <ul>
                <li>Перед деплоем меняйте версию только в <code>legion-version.php</code> и заливайте все изменённые файлы.</li>
                <li><code>api/rotation_config.php</code> не в Git — создайте на сервере из <code>rotation_config.example.php</code>.</li>
                <li>Если у тренера нет колонки — добавьте её в Google Таблицу с нужным словом в заголовке (например «скручиван»).</li>
            </ul>
        </section>
    </main>
</body>
</html>
