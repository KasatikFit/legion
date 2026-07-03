<?php
require_once __DIR__ . '/api/ranks_lib.php';
$__legionRanksPayload = legion_load_all_ranks();
?>
<script>window.__legionRanksFromServer = <?php echo json_encode($__legionRanksPayload['ranks'], JSON_UNESCAPED_UNICODE); ?>;</script>
