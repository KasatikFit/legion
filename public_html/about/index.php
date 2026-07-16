<?php
require_once dirname(__DIR__) . '/api/coaches.php';
require_once dirname(__DIR__) . '/legion-version.php';
$coaches = legion_coaches_config();
$legionVer = legion_asset_version();
$backUrl = '/';
if (!empty($_GET['from']) && isset($coaches[$_GET['from']])) {
    $backUrl = '/' . rawurlencode($_GET['from']) . '/';
}
$vkUrl = 'https://vk.com/fitness_club_samara';
$vkMessagesUrl = 'https://vk.com/im?sel=-218774228';
$phone = '+7 (917) 103-61-91';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>О нас — Легион Силы</title>
    <meta name="description" content="Спортивный клуб «Легион Силы» в Самаре: миссия, ценности и система развития через функциональные тренировки, ранги и командный дух.">
    <link rel="icon" href="/images/legion-logo.png">
    <link rel="stylesheet" href="/css/legion.css?v=<?php echo (int) $legionVer; ?>">
</head>
<body class="guide-page about-page">
    <header class="site-header no-print">
        <img src="/images/legion-logo.png" alt="Легион Самара" class="site-logo">
        <div class="site-header-text">
            <h1>О нас</h1>
            <p class="site-tagline">Легион Силы — сильнее вместе</p>
        </div>
    </header>

    <?php
    $legionNavActive = 'about';
    $legionNavCoachSlug = '';
    if (!empty($_GET['from']) && isset($coaches[$_GET['from']])) {
        $legionNavCoachSlug = (string) $_GET['from'];
    }
    require dirname(__DIR__) . '/legion-site-nav.php';
    ?>

    <main class="guide-main">
        <section class="guide-hero">
            <div class="guide-hero-img about-hero-mark">
                <img src="/images/legion-logo.png" alt="" class="about-hero-logo" width="120" height="120">
            </div>
            <div class="guide-hero-text">
                <p><strong>«Легион Силы»</strong> — сеть спортивных залов в Самаре, где дети и подростки учатся быть сильными не только в теле, но и в характере. Мы не гонимся за формальными медалями: главное — каким человеком вырастет ребёнок и что спорт даст ему в жизни.</p>
                <p class="guide-note">Наша миссия — увлечь детей движением и здоровым образом жизни, чтобы они выросли уверенными в себе мужчинами, готовыми преодолевать любые препятствия.</p>
            </div>
        </section>

        <section class="about-values">
            <h2 class="about-section-title">Во что мы верим</h2>
            <div class="about-values-grid">
                <article class="about-value-card">
                    <span class="about-value-icon" aria-hidden="true">🎯</span>
                    <h3>Цель и игра</h3>
                    <p>Ребёнку важны вызов, испытание и ощущение «я стал сильнее, чем вчера». Тренировки построены как путь с маленькими победами — а не как скучная обязанность.</p>
                </article>
                <article class="about-value-card">
                    <span class="about-value-icon" aria-hidden="true">🤝</span>
                    <h3>Команда и братство</h3>
                    <p>Спортивное братство, взаимовыручка и уважение к правилам — основа дружбы, которая остаётся далеко за пределами зала.</p>
                </article>
                <article class="about-value-card">
                    <span class="about-value-icon" aria-hidden="true">📈</span>
                    <h3>Прогресс каждый день</h3>
                    <p>Завтра будь лучше, чем вчера — на этом строятся сила, воля и характер. Мы учим прогрессии нагрузок и доводить начатое до конца.</p>
                </article>
                <article class="about-value-card">
                    <span class="about-value-icon" aria-hidden="true">🧭</span>
                    <h3>Наставники</h3>
                    <p>Над тренировками работает команда педагогов и тренеров с опытом в детском спорте, единоборствах и силовых направлениях. Сильный мужской пример — часть нашей методики.</p>
                </article>
            </div>
        </section>

        <section class="guide-section">
            <div class="guide-section-media about-illus-block" aria-hidden="true">
                <span class="about-illus-emoji">🏃</span>
            </div>
            <div class="guide-section-body">
                <h2>Для кого мы</h2>
                <p>Основное направление — <strong>мальчики и подростки 6–16 лет</strong>: от первых шагов в спорте до серьёзной функциональной подготовки. Мы берём и тех, кто «ленивый и только в телефоне», и тех, кто стесняется или боится начать.</p>
                <ul>
                    <li>Дети с нулевой подготовки — втягиваем с полного нуля</li>
                    <li>Ребята, которым нужна альтернатива гаджетам и пассивному досугу</li>
                    <li>Те, кто хочет базу для футбола, единоборств, танцев и любой активной жизни</li>
                </ul>
                <p>Также в клубе есть <strong>женские группы</strong>, персональные занятия и бокс для детей и взрослых — в зависимости от площадки.</p>
            </div>
        </section>

        <section class="guide-section guide-section--reverse">
            <div class="guide-section-media about-illus-block" aria-hidden="true">
                <span class="about-illus-emoji">🔥</span>
            </div>
            <div class="guide-section-body">
                <h2>Как проходят тренировки</h2>
                <p>Занятие — это не «побегали и разошлись». Ребята приходят в зал, настраиваются, оставляют за дверью суету и включаются в процесс.</p>
                <ol>
                    <li><strong>Разминка</strong> — готовим тело, развиваем координацию и внимание</li>
                    <li><strong>Основная часть</strong> — круговые и функциональные комплексы, сила, выносливость, скорость</li>
                    <li><strong>Игровая система</strong> — ранги, испытания, командные задания и соревнования</li>
                </ol>
                <p>Ребёнок учится не сдаваться после первой неудачи, поддерживать товарищей и видеть, как «не могу» превращается в «получилось». Частая фраза после первого занятия: <em>«А когда следующая тренировка?»</em></p>
            </div>
        </section>

        <section class="guide-section">
            <div class="guide-section-media about-illus-block" aria-hidden="true">
                <span class="about-illus-emoji">🏅</span>
            </div>
            <div class="guide-section-body">
                <h2>Система рангов и рейтинг</h2>
                <p>Мы разработали систему из <strong>60 нормативов</strong> — как список миссий, только прокачивается не персонаж в игре, а сам спортсмен: подтяни три раза, сделай двадцать отжиманий, прыгни на 130&nbsp;см и дальше по нарастающей.</p>
                <p>За каждую сдачу — новое звание и зачёт в ранговой книжке. Три лиги (бронза, серебро, золото) превращают тренировки в долгий и увлекательный путь. Параллельно работает <strong>клубный рейтинг</strong> по шести упражнениям — он виден на этом сайте и мотивирует соревноваться внутри группы и всего «Легиона».</p>
                <p>Наша цель — объединять легионеров из разных городов: чтобы ребята из Самары, Ставрополя и других филиалов соревновались в закрытии рангов и чувствовали себя частью большого сообщества.</p>
                <p><a href="/rating-info/<?php echo $legionNavCoachSlug !== '' ? '?from=' . rawurlencode($legionNavCoachSlug) : ''; ?>" class="about-inline-link">Подробнее о системе рейтинга →</a></p>
            </div>
        </section>

        <section class="guide-section guide-section--reverse">
            <div class="guide-section-media about-illus-block" aria-hidden="true">
                <span class="about-illus-emoji">💪</span>
            </div>
            <div class="guide-section-body">
                <h2>Что получает ребёнок</h2>
                <ul>
                    <li>Силу, выносливость и уверенность в себе</li>
                    <li>Активный образ жизни вместо часов у экрана</li>
                    <li>Здоровье, крепкий иммунитет и ровную осанку</li>
                    <li>Новых друзей и чувство принадлежности к команде</li>
                    <li>Гордость за достижения и желание идти дальше</li>
                </ul>
                <p>Физическое развитие напрямую влияет на энергию, дисциплину и способность учиться. Мы ориентируемся на <strong>здоровое развитие</strong>, а не на давление и выгорание: подбираем ключи к мотивации каждого.</p>
            </div>
        </section>

        <section class="guide-section guide-section--full about-contact">
            <div class="guide-section-body">
                <h2>Где мы в Самаре</h2>
                <p>У клуба несколько площадок по городу — проспект Юных Пионеров, Минская, Ветлужский переулок, проспект Кирова и другие адреса. Актуальный список залов, расписание и запись — в сообществе ВКонтакте.</p>
                <div class="about-contact-actions">
                    <a href="<?php echo htmlspecialchars($vkUrl, ENT_QUOTES, 'UTF-8'); ?>" class="guide-back-btn about-contact-btn" target="_blank" rel="noopener noreferrer">Группа ВКонтакте</a>
                    <a href="<?php echo htmlspecialchars($vkMessagesUrl, ENT_QUOTES, 'UTF-8'); ?>" class="about-contact-btn about-contact-btn--ghost" target="_blank" rel="noopener noreferrer">Написать в сообщения</a>
                </div>
                <p class="about-contact-phone">Телефон для консультации: <a href="tel:+79171036191"><?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?></a></p>
            </div>
        </section>
    </main>

    <footer class="guide-footer no-print">
        <a href="<?php echo htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8'); ?>" class="guide-back-btn">Вернуться к рейтингу</a>
    </footer>
</body>
</html>
