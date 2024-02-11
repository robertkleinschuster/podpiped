<?php

declare(strict_types=1);

require_once "flush_header.php";

$channels =  array_map(fn($name) => basename($name, '.new'), glob(__DIR__ . '/channel/*'));
$newChannels = array_map(fn($name) => basename($name, '.new'), glob(__DIR__ . '/channel/*.new'));
$locked = array_map(fn($name) => basename($name, '.lock'), glob(__DIR__ . '/static/*.lock'));
$downloads = array_map(fn($name) => basename($name, '.download'), glob(__DIR__ . '/static/*.download'));
$cached = array_map(fn($name) => basename($name, '.mp4'), glob(__DIR__ . '/static/*.mp4'));

flush();

echo "channels: " . count(array_unique($channels));
echo "\n";
flush();
echo "new channels: " . count($newChannels);
echo "\n";
flush();
echo "videos: " . count(array_diff($cached, $locked));
echo "\n";
flush();
echo "download queue: " . count(array_diff($downloads, $locked));
echo "\n";
flush();
echo "download in progress: " . count($locked);
echo "\n";
flush();

exit;