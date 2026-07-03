<?php
require_once dirname(__DIR__) . '/api/coaches.php';
require_once __DIR__ . '/illustrations.php';
$coaches = legion_coaches_config();
$legionVer = 22;
$backUrl = '/';
if (!empty($_GET['from']) && isset($coaches[$_GET['from']])) {
    $backUrl = '/' . rawurlencode($_GET['from']) . '/';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>О системе рейтинга — Легион Силы</title>
    <link rel="icon" href="/images/legion-logo.png">
    <link rel="stylesheet" href="/css/legion.css?v=<?php echo (int)$legionVer; ?>">
</head>
<body class="guide-page">
    <header class="site-header no-print">
        <img src="/images/legion-logo.png" alt="Легион Самара" class="site-logo">
        <div class="site-header-text">
            <h1>О системе рейтинга</h1>
            <p class="site-tagline">Легион Силы — как всё устроено</p>
        </div>
    </header>

    <nav class="navbar no-print guide-nav">
        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="nav-tab">← К рейтингу</a>
        <a href="/" class="nav-tab">Общий рейтинг</a>
        <div class="dropdown">
            <button type="button" class="dropbtn" aria-haspopup="true" aria-expanded="false">Тренеры ▼</button>
            <div class="dropdown-content">
                <?php foreach ($coaches as $slug => $coach): ?>
                <a href="/<?php echo htmlspecialchars($slug); ?>/"><?php echo legion_coach_nav_icon(); ?> <?php echo htmlspecialchars($coach['name']); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </nav>

    <main class="guide-main">
        <section class="guide-hero">
            <div class="guide-hero-img"><?php echo legion_guide_illustration('hero', 'Рейтинг Легиона Силы'); ?></div>
            <div class="guide-hero-text">
                <p>Рейтинг Легиона Силы — это прозрачная система соревнования между спортсменами клуба. Результаты по упражнениям превращаются в баллы, из которых складывается общее место, звания лиг и элита клуба.</p>
                <p class="guide-note">Данные подгружаются из Google Таблиц тренеров и обновляются автоматически.</p>
            </div>
        </section>

        <section class="guide-section">
            <div class="guide-section-media">
                <?php echo legion_guide_illustration('points', 'Схема начисления баллов за место'); ?>
            </div>
            <div class="guide-section-body">
                <h2>Как начисляются баллы</h2>
                <p>В каждом упражнении спортсмен получает баллы за занятое <strong>место</strong> среди участников (у тренера — в своей группе, в общем рейтинге — среди всего клуба).</p>
                <ul>
                    <li><strong>1-е место</strong> — 100 баллов</li>
                    <li><strong>2-е место</strong> — 95 баллов</li>
                    <li><strong>3-е место</strong> — 90 баллов</li>
                    <li><strong>4-е и далее</strong> — 88, 86, 84… с шагом −2 до 0</li>
                </ul>
                <p>При одинаковом результате место <strong>делится</strong>, и все спортсмены с этим результатом получают одинаковые баллы.</p>
                <p><strong>Сумма баллов</strong> по всем упражнениям — это итоговый рейтинг спортсмена.</p>
            </div>
        </section>

        <section class="guide-section guide-section--reverse">
            <div class="guide-section-media">
                <?php echo legion_guide_illustration('exercises', 'Шесть упражнений рейтинга'); ?>
            </div>
            <div class="guide-section-body">
                <h2>Упражнения рейтинга</h2>
                <p>У каждого спортсмена учитываются <strong>6 показателей</strong>. По каждому строится отдельная таблица и начисляются баллы:</p>
                <ol class="guide-exercise-list">
                    <li>Отжимания</li>
                    <li>Подтягивания</li>
                    <li>Вис (секунды)</li>
                    <li>Бёрпи за 1 минуту</li>
                    <li>Скручивания</li>
                    <li>Прыжок в длину (см)</li>
                </ol>
                <p>На вкладках рейтинга можно посмотреть лидеров по каждому упражнению отдельно.</p>
            </div>
        </section>

        <section class="guide-section">
            <div class="guide-section-media">
                <?php echo legion_guide_illustration('coach-group', 'Рейтинг внутри группы тренера'); ?>
            </div>
            <div class="guide-section-body">
                <h2>Рейтинг у тренера</h2>
                <p>На странице каждого тренера баллы считаются <strong>только среди его воспитанников</strong>. Так видно, кто сильнее в своей команде.</p>
                <ul>
                    <li><strong>Элита группы</strong> — топ-10 спортсменов тренера по сумме баллов (отмечены 🛡️)</li>
                    <li><strong>Любители</strong> — остальные участники группы</li>
                    <li>Раз в месяц состав элиты может обновляться по правилам ротации</li>
                </ul>
            </div>
        </section>

        <section class="guide-section guide-section--reverse">
            <div class="guide-section-media">
                <?php echo legion_guide_illustration('top25', 'ТОП-25 элита клуба'); ?>
            </div>
            <div class="guide-section-body">
                <h2>Общий рейтинг клуба</h2>
                <p>На главной странице все спортсмены клуба соревнуются в <strong>общем зачёте</strong>. Принцип баллов тот же, но места считаются среди всех.</p>
                <ul>
                    <li><strong>ТОП-25 Легиона Силы</strong> — 25 лучших по сумме баллов, элита клуба</li>
                    <li><strong>Легионеры</strong> — спортсмены с 26-го места и ниже</li>
                </ul>
            </div>
        </section>

        <section class="guide-section">
            <div class="guide-section-media">
                <?php echo legion_guide_illustration('ranks', 'Три лиги рангов'); ?>
            </div>
            <div class="guide-section-body">
                <h2>Ранги и лиги</h2>
                <p>Параллельно с рейтингом по баллам у спортсмена есть <strong>клубный ранг</strong> — путь через три лиги:</p>
                <ul>
                    <li>🥉 <strong>Бронзовая лига</strong> — 20 упражнений-нормативов</li>
                    <li>🥈 <strong>Серебряная лига</strong> — ещё 20 упражнений</li>
                    <li>🥇 <strong>Золотая лига</strong> — финальные 20 упражнений</li>
                </ul>
                <p>Каждое выполненное упражнение открывает новое звание. Нажмите на ранг в таблице или в карточке спортсмена, чтобы увидеть прогресс и список нормативов.</p>
            </div>
        </section>

        <section class="guide-section guide-section--reverse">
            <div class="guide-section-media">
                <?php echo legion_guide_illustration('hall', 'Зал славы и рекорды'); ?>
            </div>
            <div class="guide-section-body">
                <h2>Зал славы и достижения</h2>
                <p><strong>Зал славы</strong> — лучшие результаты клуба по каждому упражнению за всё время и лента недавних рекордов.</p>
                <p><strong>Достижения</strong> в карточке спортсмена — награды за ТОП-1, ТОП-3, ТОП-25 и лидерство в отдельных упражнениях.</p>
            </div>
        </section>

        <section class="guide-section guide-section--full">
            <div class="guide-section-body">
                <h2>Если сумма баллов равна</h2>
                <p>При равенстве итоговых баллов места сравниваются по результатам в таком порядке:</p>
                <p class="guide-tiebreak">Подтягивания → Отжимания → Вис → Бёрпи → Скручивания → Прыжок</p>
            </div>
            <div class="guide-section-media guide-section-media--center">
                <?php echo legion_guide_illustration('tiebreak', 'Порядок сравнения при равных баллах'); ?>
            </div>
        </section>
    </main>

    <footer class="guide-footer no-print">
        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="guide-back-btn">Вернуться к рейтингу</a>
    </footer>

    <script>
    (function () {
        document.querySelectorAll('.dropdown .dropbtn').forEach(function (btn) {
            var dropdown = btn.closest('.dropdown');
            var menu = dropdown.querySelector('.dropdown-content');
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var isOpen = dropdown.classList.contains('open');
                document.querySelectorAll('.dropdown.open').forEach(function (d) {
                    d.classList.remove('open');
                    var b = d.querySelector('.dropbtn');
                    if (b) b.setAttribute('aria-expanded', 'false');
                });
                if (!isOpen) {
                    dropdown.classList.add('open');
                    btn.setAttribute('aria-expanded', 'true');
                }
            });
            menu.addEventListener('click', function (e) { e.stopPropagation(); });
        });
        document.addEventListener('click', function () {
            document.querySelectorAll('.dropdown.open').forEach(function (d) {
                d.classList.remove('open');
                var b = d.querySelector('.dropbtn');
                if (b) b.setAttribute('aria-expanded', 'false');
            });
        });
    })();
    </script>
</body>
</html>
