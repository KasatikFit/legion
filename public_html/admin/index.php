<?php
require_once dirname(__DIR__) . '/legion-version.php';
require_once dirname(__DIR__) . '/api/admin_auth_lib.php';

$legionVer = legion_asset_version();
$adminConfigured = legion_admin_auth_is_configured();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Суперадмин — Легион Силы</title>
    <link rel="icon" href="/images/legion-logo.png">
    <?php require dirname(__DIR__) . '/legion-head-fonts.php'; ?>
    <link rel="stylesheet" href="/css/legion.css?v=<?php echo (int)$legionVer; ?>">
    <link rel="stylesheet" href="/css/admin.css?v=<?php echo (int)$legionVer; ?>">
</head>
<body class="admin-page">
    <header class="site-header no-print">
        <img src="/images/legion-logo.png" alt="Легион Самара" class="site-logo">
        <div class="site-header-text">
            <span class="admin-badge">Суперадмин</span>
            <h1>Управление тренерами</h1>
            <p class="site-tagline">Добавление групп, пароли, видимость на главной</p>
        </div>
    </header>

    <?php
    $legionNavMode = 'admin';
    $legionNavActive = 'admin';
    require dirname(__DIR__) . '/legion-site-nav.php';
    ?>

    <main class="admin-main" id="admin-root">
        <section class="admin-card" id="admin-login-card">
            <h2>Вход суперадмина</h2>
            <?php if (!$adminConfigured): ?>
            <p class="admin-error">На сервере не настроен пароль.<br>
            Скопируйте <code>api/admin_auth.example.php</code> → <code>api/admin_auth.php</code> и задайте пароль.</p>
            <?php else: ?>
            <p class="admin-note">Введите пароль суперадмина клуба.</p>
            <form id="admin-login-form" class="admin-form">
                <label>
                    <span>Пароль</span>
                    <input type="password" id="admin-login-password" autocomplete="current-password" required>
                </label>
                <button type="submit" class="admin-btn admin-btn--primary">Войти</button>
            </form>
            <p id="admin-login-error" class="admin-error" hidden></p>
            <?php endif; ?>
        </section>

        <div id="admin-app" hidden>
            <section class="admin-card" id="admin-diagnostics-card">
                <div class="admin-diag-header">
                    <h2>Состояние системы</h2>
                    <button type="button" class="admin-btn admin-btn--small" id="admin-diagnostics-refresh">Обновить</button>
                </div>
                <div id="admin-diagnostics-body">
                    <p class="admin-note">Проверка…</p>
                </div>
            </section>

            <section class="admin-card">
                <h2>Добавить тренера</h2>
                <form id="admin-create-form" class="admin-form admin-form--grid">
                    <label>
                        <span>Фамилия и имя</span>
                        <input type="text" id="admin-create-name" placeholder="Иванов Иван" required>
                    </label>
                    <label>
                        <span>Slug (адрес папки)</span>
                        <input type="text" id="admin-create-slug" placeholder="ivanov-ivan" pattern="[a-z0-9\-]+">
                        <small class="admin-hint">Латиница: /slug/ и /slug/training.php</small>
                    </label>
                    <label>
                        <span>Пароль режима тренировки</span>
                        <input type="text" id="admin-create-password" minlength="4" required>
                    </label>
                    <div class="admin-form-actions">
                        <button type="submit" class="admin-btn admin-btn--primary">Создать группу</button>
                    </div>
                </form>
                <p id="admin-create-status" class="admin-status" hidden></p>
            </section>

            <section class="admin-card">
                <h2>Тренеры клуба</h2>
                <div class="table-wrap">
                    <table class="rating-table admin-table" id="admin-coaches-table">
                        <thead>
                            <tr>
                                <th>Имя</th>
                                <th>Slug</th>
                                <th>На главной</th>
                                <th>Пароль</th>
                                <th>Ссылки</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="admin-coaches-tbody">
                            <tr><td colspan="6">Загрузка…</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <script>
    window.__legionAdminConfigured = <?php echo $adminConfigured ? 'true' : 'false'; ?>;
    </script>
    <script src="/js/legion-admin.js?v=<?php echo (int)$legionVer; ?>" defer></script>
</body>
</html>
