<?php

declare(strict_types=1);

set_time_limit(0);

header('content-type: text/plan; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Surrogate-Control: BigPipe/1.0');
header('X-Accel-Buffering: no');
header('Set-Cookie: rid=' . uniqid());
if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', '1');
}
http_response_code(200);
ob_start();
echo "processing\n\n";
header('Connection: close');
header('Content-Length: ' . ob_get_length());
ob_end_flush();
@ob_flush();
flush();

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

$status = require "status.php";
file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'status', $status);
try {
    $run = $_GET['run'] ?? 1;
    if ($run <= 2) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]?run=" . $run + 1,
            CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'],
        ]);

        curl_exec($ch);
        curl_close($ch);
    }
} catch (Throwable $exception) {
    error_log((string)$exception);
}
