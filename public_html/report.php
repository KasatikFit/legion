<?php
/**
 * Закрытый отчёт по секретной ссылке — любая группа тренера.
 *
 * /report.php?token=...&coach=slug-группы&id=42
 * /report.php?token=...&coach=slug-группы&name=Фамилия%20Имя  (старый формат)
 * /report.php?token=...&coach=slug-группы&id=42&ai=1
 * /report.php?token=...&coach=slug-группы  — список спортсменов группы
 */
require_once __DIR__ . '/legion-version.php';
require_once __DIR__ . '/api/report_lib.php';

legion_report_serve_request(array(
    'reportBasePath' => '/report.php',
    'cssVersion' => legion_asset_version(),
));
