<?php

declare(strict_types=1);

header('content-type: text/plan; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Set-Cookie: rid=' . uniqid());

http_response_code(200);
flush();
ob_start();
echo "processing\n\n";
ob_flush();
set_time_limit(45);
