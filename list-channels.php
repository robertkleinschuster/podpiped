<?php

require_once "CachedClient.class.php";
require_once "Channel.class.php";


$channels = array_filter(
    array_map('basename', glob(__DIR__ . '/channel/*')),
    fn(string $id) => !str_ends_with($id, '.new')
);
$client = new Client($_SERVER['HTTP_HOST']);
$cachedClient = new CachedClient($client);

/** @var Channel[] $channels */
$channels = array_filter(array_map(fn(string $id) => $cachedClient->channelInfo($id), $channels));

usort($channels, fn(Channel $a, Channel $b) => strcasecmp($a->getTitle(), $b->getTitle()));

header('Content-Type: text/plain');
foreach ($channels as $channel) {
    printf("%s: https://www.youtube.com/channel/%s\n", $channel->getTitle(), $channel->getId());
}
