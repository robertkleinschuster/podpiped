<?php

declare(strict_types=1);

require_once "flush_header.php";
require_once "Downloader.class.php";

echo "\ndownloading files\n";

$downloader = new Downloader();
$downloader->download();

$status = require "status.php";
file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'status', $status);
flush();
exit;