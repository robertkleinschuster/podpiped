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
