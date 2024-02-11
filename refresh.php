<?php

declare(strict_types=1);

require_once "flush_header.php";
require_once "Client.class.php";
require_once "CachedClient.class.php";

echo "\nrefreshing cached channels\n";

$client = new Client($_SERVER['HTTP_HOST']);
$cachedClient = new CachedClient($client);

$time = time();
do {
    $complete = $cachedClient->refresh();
    flush();
} while (!$complete && time() - $time < 30);

exit;