<?php

declare(strict_types=1);

require_once "flush_header.php";
require_once "ImageConverter.class.php";

echo "\nconverting images\n";

$converter = new ImageConverter();
$converter->convert();

$status = require "status.php";
file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'status', $status);
exit;