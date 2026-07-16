<?php
/**
 * Навигация сайта (две строки на публичных страницах).
 *
 * $legionNavActive — club | coach | training | rating-info | about | events | diagnostics | admin
 * $legionNavCoachSlug — slug текущей группы
 * $legionNavExtraLinks — доп. ссылки (диагностика)
 * $legionNavMode — site | admin | diagnostics
 */
if (!isset($coaches)) {
    require_once __DIR__ . '/api/coaches.php';
    $coaches = legion_coaches_config();
}

$legionNavActive = isset($legionNavActive) ? (string) $legionNavActive : '';
$legionNavCoachSlug = isset($legionNavCoachSlug) ? (string) $legionNavCoachSlug : '';
$legionNavMode = isset($legionNavMode) ? (string) $legionNavMode : 'site';
$legionNavExtraLinks = isset($legionNavExtraLinks) && is_array($legionNavExtraLinks) ? $legionNavExtraLinks : array();

if (!function_exists('legion_site_nav_link')) {
    function legion_site_nav_link($href, $label, $isActive, $extraClass = '') {
        $class = trim('legion-site-nav__link' . ($isActive ? ' is-active' : '') . ($extraClass !== '' ? ' ' . $extraClass : ''));
        $safeLabel = htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8');
        if ($isActive) {
            echo '<span class="' . $class . '" aria-current="page">' . $safeLabel . '</span>';
            return;
        }
        echo '<a href="' . htmlspecialchars((string) $href, ENT_QUOTES, 'UTF-8') . '" class="' . $class . '">' . $safeLabel . '</a>';
    }
}

if (!function_exists('legion_site_nav_disabled')) {
    function legion_site_nav_disabled($label, $hint = 'В разработке') {
        $safeLabel = htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8');
        $safeHint = htmlspecialchars((string) $hint, ENT_QUOTES, 'UTF-8');
        echo '<span class="legion-site-nav__link is-disabled" title="' . $safeHint . '" aria-disabled="true">';
        echo $safeLabel;
        echo '<span class="legion-site-nav__soon">скоро</span>';
        echo '</span>';
    }
}

if (!function_exists('legion_site_nav_coach_tile')) {
    function legion_site_nav_coach_tile($href, $icon, $name, $isActive) {
        $class = 'legion-site-nav__coach-tile' . ($isActive ? ' is-active' : '');
        $safeName = htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8');
        $safeHref = htmlspecialchars((string) $href, ENT_QUOTES, 'UTF-8');
        echo '<a href="' . $safeHref . '" class="' . $class . '"' . ($isActive ? ' aria-current="page"' : '') . '>';
        echo '<span class="legion-site-nav__coach-icon" aria-hidden="true">' . $icon . '</span>';
        echo '<span class="legion-site-nav__coach-name">' . $safeName . '</span>';
        echo '</a>';
    }
}

$ratingInfoHref = '/rating-info/';
$aboutHref = '/about/';
if ($legionNavCoachSlug !== '') {
    $ratingInfoHref .= '?from=' . rawurlencode($legionNavCoachSlug);
    $aboutHref .= '?from=' . rawurlencode($legionNavCoachSlug);
}
?>
<nav class="legion-site-nav no-print<?php echo $legionNavMode === 'site' ? ' legion-site-nav--split' : ''; ?>" aria-label="Навигация по сайту">
<?php if ($legionNavMode === 'admin'): ?>
    <div class="legion-site-nav__primary">
        <?php legion_site_nav_link('/', 'Общий рейтинг', false); ?>
        <?php legion_site_nav_link('/diagnostics/', 'Диагностика', false); ?>
        <?php legion_site_nav_link('/admin/', 'Суперадмин', $legionNavActive === 'admin'); ?>
        <button type="button" class="legion-site-nav__link legion-site-nav__btn" id="admin-logout-btn" hidden>Выйти</button>
    </div>
<?php elseif ($legionNavMode === 'diagnostics'): ?>
    <div class="legion-site-nav__primary">
        <?php legion_site_nav_link('/', 'Общий рейтинг', false); ?>
        <?php legion_site_nav_link('/diagnostics/', 'Диагностика', $legionNavActive === 'diagnostics'); ?>
        <?php legion_site_nav_link('/admin/', 'Суперадмин', false); ?>
        <?php foreach ($legionNavExtraLinks as $item): ?>
            <?php
            legion_site_nav_link(
                isset($item['href']) ? $item['href'] : '#',
                isset($item['label']) ? $item['label'] : '',
                !empty($item['active'])
            );
            ?>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="legion-site-nav__primary">
        <?php legion_site_nav_link('/', 'Общий рейтинг', $legionNavActive === 'club'); ?>
        <?php legion_site_nav_link($ratingInfoHref, 'О системе рейтинга', $legionNavActive === 'rating-info'); ?>
        <?php legion_site_nav_link($aboutHref, 'О нас', $legionNavActive === 'about'); ?>
        <?php legion_site_nav_disabled('События'); ?>
    </div>
    <div class="legion-site-nav__coaches">
        <div class="legion-site-nav__coaches-label">Тренеры</div>
        <div class="legion-site-nav__coaches-grid">
            <?php foreach ($coaches as $slug => $coach): ?>
                <?php
                legion_site_nav_coach_tile(
                    '/' . rawurlencode($slug) . '/',
                    legion_coach_nav_icon(),
                    $coach['name'],
                    $legionNavActive === 'coach' && $slug === $legionNavCoachSlug
                );
                ?>
            <?php endforeach; ?>
            <?php if ($legionNavCoachSlug !== '' && isset($coaches[$legionNavCoachSlug])): ?>
                <a href="/<?php echo htmlspecialchars($legionNavCoachSlug, ENT_QUOTES, 'UTF-8'); ?>/training.php"
                   class="legion-site-nav__coach-tile legion-site-nav__coach-tile--training<?php echo $legionNavActive === 'training' ? ' is-active' : ''; ?>"
                   title="Режим тренировки"
                   <?php echo $legionNavActive === 'training' ? 'aria-current="page"' : ''; ?>>
                    <span class="legion-site-nav__coach-icon" aria-hidden="true">📝</span>
                    <span class="legion-site-nav__coach-name">Тренировка</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
</nav>
