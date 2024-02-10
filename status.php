<?php

declare(strict_types=1);

header('content-type: text/plan');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
http_response_code(200);
flush();

$locked = glob(__DIR__ . '/static/*.lock');
$downloads = glob(__DIR__ . '/static/*.download');
$channels = glob(__DIR__ . '/channel/*');
$cached = glob(__DIR__ . '/static/*.mp4');

echo "channels: " . count($channels);
echo "\n";
echo "download queue: " . count($downloads);
echo "\n";
echo "in progress: " . count($locked);
echo "\n";
echo "cached: " . count($cached);