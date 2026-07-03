<?php
require_once __DIR__ . '/api/coaches.php';
$legionVer = 19;
?>
<script>window.LegionCoachesFromServer = <?php echo json_encode(legion_coaches_for_js(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;</script>
<script src="/js/legion-config.js?v=<?php echo (int)$legionVer; ?>"></script>
<script src="/js/legion-core.js?v=<?php echo (int)$legionVer; ?>"></script>
<script src="/js/legion-ui.js?v=<?php echo (int)$legionVer; ?>"></script>
