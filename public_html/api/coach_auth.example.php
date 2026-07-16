<?php
/**
 * Резервные пароли режима тренировки (если не заданы в MySQL legion_coach_auth).
 * Скопируйте в coach_auth.php на сервере (не в Git).
 *
 * Новых тренеров удобнее добавлять через /admin/ — пароль сразу попадёт в MySQL.
 * Приоритет: legion_coach_auth (БД) → этот файл.
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
