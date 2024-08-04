<?php

declare(strict_types=1);

require_once "flush_header.php";
require_once "Client.class.php";
require_once "CachedClient.class.php";

$start = $_GET['start'] ?? time();
echo "\nrefreshing cached channels/playlists\n";

$client = new Client($_SERVER['HTTP_HOST']);
$cachedClient = new CachedClient($client);

$cachedClient->refreshPlaylists();
$status = require "status.php";
file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'status', $status);

$cachedClient->refreshChannels();
$status = require "status.php";
file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'status', $status);

sleep(10);
if (time() - $start < 270) {
    try {
        $url = "https://$_SERVER[HTTP_HOST]/refresh.php?start=" . $start;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'],
        ]);
        curl_exec($ch);
        curl_close($ch);
        $log = new Log();
        $log->append('rerun: ' . $url);
    } catch (Throwable $exception) {
        error_log((string)$exception);
    }
}
exit;