<?php

declare(strict_types=1);

header('content-type: text/plan');
header('Cache-Control: no-cache, no-store, must-revalidate, post-check=0, max-age=0');
header('Pragma: no-cache');
http_response_code(200);
flush();

$channels =  array_map(fn($name) => basename($name, '.new'), glob(__DIR__ . '/channel/*'));
$newChannels = array_map(fn($name) => basename($name, '.new'), glob(__DIR__ . '/channel/*.new'));
$locked = array_map(fn($name) => basename($name, '.lock'), glob(__DIR__ . '/static/*.lock'));
$downloads = array_map(fn($name) => basename($name, '.download'), glob(__DIR__ . '/static/*.download'));
$cached = array_map(fn($name) => basename($name, '.mp4'), glob(__DIR__ . '/static/*.mp4'));

echo "channels: " . count(array_unique($channels));
echo "\n";
echo "new channels: " . count($newChannels);
echo "\n";
echo "videos: " . count(array_diff($cached, $locked));
echo "\n";
echo "download queue: " . count(array_diff($downloads, $locked));
echo "\n";
echo "download in progress: " . count($locked);
echo "\n";
