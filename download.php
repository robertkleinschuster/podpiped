<?php

declare(strict_types=1);

require_once "flush_header.php";
require_once "Downloader.class.php";

echo "\ndownloading files\n";
$start = $_GET['start'] ?? time();

$downloader = new Downloader();
$downloader->download();

$status = require "status.php";
file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'status', $status);
exit;
if (time() - $start < 250) {
    sleep(1);
    try {
        $url = "https://$_SERVER[HTTP_HOST]/download.php?start=" . $start;
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