<?php

declare(strict_types=1);

$channels =  array_map(fn($name) => basename($name, '.new'), glob(__DIR__ . '/channel/*'));
$newChannels = array_map(fn($name) => basename($name, '.new'), glob(__DIR__ . '/channel/*.new'));
$playlists =  array_map(fn($name) => basename($name, '.new'), glob(__DIR__ . '/playlist/*'));
$newPlaylists = array_map(fn($name) => basename($name, '.new'), glob(__DIR__ . '/playlist/*.new'));
$locked = array_map(fn($name) => basename($name, '.lock'), glob(__DIR__ . '/static/*.lock'));
$downloads = array_map(fn($name) => basename($name, '.download'), glob(__DIR__ . '/static/*.download'));
$cached = array_map(fn($name) => basename($name, '.mp4'), glob(__DIR__ . '/static/*.mp4'));

$result = "channels: " . count(array_unique($channels));
$result .= "\n";
$result .= "new channels: " . count($newChannels);
$result .= "\n";
$result .= "playlists: " . count(array_unique($playlists));
$result .= "\n";
$result .= "new playlists: " . count($newPlaylists);
$result .= "\n";
$result .= "videos: " . count(array_diff($cached, $locked));
$result .= "\n";
$result .= "download queue: " . count(array_diff($downloads, $locked));
$result .= "\n";
$result .= "download in progress: " . count($locked);
$result .= "\n";

$bytes = disk_free_space(__DIR__);
$si_prefix = array( 'B', 'KB', 'MB', 'GB', 'TB', 'EB', 'ZB', 'YB' );
$base = 1024;
$class = min((int)log($bytes , $base) , count($si_prefix) - 1);
$result .= sprintf('disk free: %1.2f', $bytes / pow($base, $class)) . ' ' . $si_prefix[$class];
$result .= "\n";

return $result;