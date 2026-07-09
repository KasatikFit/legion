<?php

require_once dirname(__DIR__) . '/pilot_avatars_lib.php';

$index = isset($_GET['i']) ? (int) $_GET['i'] : 0;

if ($index < 1 && isset($_GET['name']) && $_GET['name'] !== '') {
    require_once dirname(__DIR__) . '/pilot_lib.php';
    $index = legion_pilot_default_avatar_index((string) $_GET['name']);
}

if ($index < 1) {
    $index = 1;
}

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=604800');
header('X-Content-Type-Options: nosniff');

echo legion_pilot_avatar_svg_content($index);
