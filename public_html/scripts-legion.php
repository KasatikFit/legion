<?php
require_once __DIR__ . '/legion-version.php';
require_once __DIR__ . '/api/coaches.php';
$legionVer = legion_asset_version();
?>
<script>window.LegionCoachesFromServer = <?php echo json_encode(legion_coaches_for_js(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;</script>
<?php require __DIR__ . '/legion-ranks-preload.php'; ?>
<script src="/js/legion-config.js?v=<?php echo (int)$legionVer; ?>"></script>
<script src="/js/legion-core.js?v=<?php echo (int)$legionVer; ?>"></script>
<script src="/js/legion-ui.js?v=<?php echo (int)$legionVer; ?>"></script>
