<?php

declare(strict_types=1);

require_once "flush_header.php";
require_once "Log.class.php";
require_once "Downloader.class.php";

$log = new Log();
$log->clear();

$downloader = new Downloader();
$downloader->cleanup();
exit;