<?php

declare(strict_types=1);

require_once "flush_header.php";
require_once "Downloader.class.php";

echo "\ndownloading files\n";

$downloader = new Downloader();
$downloader->download();

flush();
exit;