<?php
/**
 * Пароли режима тренировки для каждого тренера.
 * Скопируйте в coach_auth.php на сервере (не в Git).
 *
 * Для каждого slug из api/coaches.php задайте пароль или хэш:
 * password_hash('ваш-пароль', PASSWORD_DEFAULT)
 */
return array(
    'yakutin' => array(
        'password' => 'change-me-yakutin',
    ),
    'nikonov' => array(
        'password' => 'change-me-nikonov',
    ),
    'kasatkin' => array(
        'password' => 'change-me-kasatkin',
    ),
    'parkhaev' => array(
        'password' => 'change-me-parkhaev',
    ),
    'makarenkov' => array(
        'password' => 'change-me-makarenkov',
    ),
    'kostin' => array(
        'password' => 'change-me-kostin',
    ),
    'pilot-demo' => array(
        'password' => 'pilot2026',
    ),
);
