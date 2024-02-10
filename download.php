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
header('Pragma: no-cache');
http_response_code(200);
flush();

echo "\ndownloading files\n";

$downloader = new Downloader();
$downloader->download();

exit;