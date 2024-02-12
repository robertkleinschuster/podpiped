<?php

declare(strict_types=1);

require_once "flush_header.php";
require_once "Downloader.class.php";

echo "\ndownloading files\n";

$downloader = new Downloader();
$downloader->download();

$status = require "status.php";
file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'status', $status);

try {
    $run = $_GET['run'] ?? 1;
    if ($run <= 2) {
        $url = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]?run=" . $run + 1;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'],
        ]);

        curl_exec($ch);
        curl_close($ch);
        $log = new Log();
        $log->append('rerun: ' . $url);
    }
} catch (Throwable $exception) {
    error_log((string)$exception);
}

exit;