<?php
/**
 * Закрытый отчёт пилотной группы (coach=pilot-demo по умолчанию).
 *
 * /pilot-demo/report.php?token=...&name=Фамилия%20Имя
 * /pilot-demo/report.php?token=...&name=...&ai=1
 */
require_once dirname(__DIR__) . '/legion-version.php';
require_once dirname(__DIR__) . '/api/report_lib.php';

legion_report_serve_request(array(
    'defaultCoachSlug' => LEGION_PILOT_SLUG,
    'reportBasePath' => '/pilot-demo/report.php',
    'omitCoachInUrl' => true,
    'cssVersion' => legion_asset_version(),
));
