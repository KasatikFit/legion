<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/admin_auth_lib.php';

legion_admin_require_auth_json();

require_once dirname(__DIR__) . '/diagnostics_lib.php';
require_once dirname(__DIR__) . '/page_data_lib.php';

$report = legion_diagnostics_run();

$loadWarnings = array();
try {
    $clubData = legion_build_page_data(null);
    if (!empty($clubData['warnings']) && is_array($clubData['warnings'])) {
        $loadWarnings = $clubData['warnings'];
    }
} catch (Exception $e) {
    $loadWarnings[] = array(
        'coach' => 'Общий рейтинг',
        'slug' => '',
        'message' => $e->getMessage(),
    );
}

$issues = array();
foreach ($report['checks'] as $group) {
    $groupName = isset($group['group']) ? $group['group'] : '';
    foreach ($group['items'] as $item) {
        $status = isset($item['status']) ? $item['status'] : 'error';
        if ($status === 'ok') {
            continue;
        }
        $issues[] = array(
            'group' => $groupName,
            'name' => isset($item['name']) ? $item['name'] : '',
            'status' => $status,
            'detail' => isset($item['detail']) ? $item['detail'] : '',
        );
    }
}

echo json_encode(array(
    'success' => true,
    'checkedAt' => $report['checkedAt'],
    'version' => $report['version'],
    'summary' => $report['summary'],
    'issues' => $issues,
    'loadWarnings' => $loadWarnings,
), JSON_UNESCAPED_UNICODE);
