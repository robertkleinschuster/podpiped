<?php

declare(strict_types=1);

set_time_limit(0);
ini_set('max_execution_time', '0');

require_once "Downloader.class.php";
require_once "ImageConverter.class.php";
require_once "Client.class.php";
require_once "CachedClient.class.php";

header('content-type: text/plan');
header('Cache-Control: no-cache, no-store, must-revalidate');
http_response_code(200);
flush();

echo "\nrefreshing cached channels\n";
ob_flush();
$client = new Client($_SERVER['HTTP_HOST']);
$cachedClient = new CachedClient($client);
$cachedClient->refresh();

echo "\ndownloading files\n";
ob_flush();
$downloader = new Downloader();
$downloader->download();

echo "\nconverting images\n";
ob_flush();
$converter = new ImageConverter();
$converter->convert();

$videos = glob(__DIR__ . '/static/*.mp4');
foreach ($videos as $video) {
    if (file_exists($video)) {
        $age = time() . filemtime($video);
        if ($age > 259200 && file_exists($video)) {
            unlink($video);
        }
    }
}

exit;