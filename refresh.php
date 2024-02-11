<?php

declare(strict_types=1);

require_once "flush_header.php";
require_once "Client.class.php";
require_once "CachedClient.class.php";

echo "\nrefreshing cached channels/playlists\n";

$client = new Client($_SERVER['HTTP_HOST']);
$cachedClient = new CachedClient($client);

$time = time();

do {
    $complete = $cachedClient->refreshPlaylists();
    flush();
    $status = require "status.php";
    file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'status', $status);
} while (!$complete && time() - $time < 30);

do {
    $complete = $cachedClient->refreshChannels();
    flush();
    $status = require "status.php";
    file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'status', $status);
} while (!$complete && time() - $time < 30);

exit;