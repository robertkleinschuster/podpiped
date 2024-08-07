<?php

require_once "Settings.class.php";
require_once "CachedClient.class.php";
require_once "DiskSpace.class.php";
require_once "Channel.class.php";

locale_set_default('de_AT');
date_default_timezone_set('Europe/Vienna');

$channelId = trim($_GET['id'] ?? '');
$channelId = preg_replace('/[^a-zA-Z0-9_\-.]/', '', $channelId);

if ($channelId) {
    require_once "channel_settings.php";
    exit;
}

$diskSpace = new DiskSpace();
$folderSize = number_format($diskSpace->getSize(__DIR__), 2, ',', '.');

$channels = array_filter(
    array_map('basename', glob(__DIR__ . '/channel/*')),
    fn(string $id) => !str_ends_with($id, '.new')
);

$client = new Client($_SERVER['HTTP_HOST']);
$cachedClient = new CachedClient($client);
$settings = new Settings();

/** @var Channel[] $channels */
$channels = array_filter(array_map(fn(string $id) => $cachedClient->channelInfo($id), $channels));

usort($channels, fn(Channel $a, Channel $b) => strcasecmp($a->getTitle(), $b->getTitle()));

$refreshedCount = 0;
foreach ($channels as $channel) {
    if (!$channel->isRefreshing()) {
        $refreshedCount++;
    }
}

$channelCount = count($channels);

/** @var Channel[] $refreshQueue */
$refreshQueue = array_filter(array_map(fn(string $id) => $cachedClient->channelInfo($id), $cachedClient->listChannels()), fn(?Channel $channel) => $channel?->isRefreshing());

header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //   $cachedClient->refreshChannel($channelId);

    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}
?>

<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Einstellungen</title>
    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --text: #000;
            --bg: #fff;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --text: #fff;
                --bg: #000;
            }
        }

        body {
            font-size: 16px;
            font-family: sans-serif;
            background-color: var(--bg);
            color: var(--text);
            display: flex;
            flex-direction: column;
        }

        button {
            font-size: 16px;
            padding: .5rem;
            border-radius: 8px;
            border: none;
            background: #2146d5;
            color: white;
            width: 100%;
        }

        a, a:visited {
            color: inherit;
        }

        summary {
            display: flex;
            align-items: baseline;
            border-bottom: 1px solid var(--text);
            padding: .5rem;
        }

        summary > span {
            display: inline-flex;
            flex-grow: 1;
            align-items: baseline;
            justify-content: space-between;
        }

        details p {
            display: flex;
            flex-direction: column;
            padding-left: 1rem;
        }
    </style>
    <style>
        .spinner {
            display: inline-block;
            vertical-align: middle;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            border: 3.8px solid;
            border-color: #ffffff;
            border-right-color: #496fff;
            animation: spinner-d3wgkg 1s infinite linear;
        }

        @keyframes spinner-d3wgkg {
            to {
                transform: rotate(1turn);
            }
        }
    </style>
</head>
<body>
<h1>Einstellungen</h1>
<p>Speicher: <?= $folderSize ?> GB / 9 GB</p>
<p>Aktualisierte Kanäle: <?= $refreshedCount ?> / <?= $channelCount ?></p>
<?php foreach ($channels as $channel): ?>
    <details>
        <summary>
            <span>
                <span><?= $channel->getTitle() ?></span>
                <span><?= $channel->isDownloadEnabled() ? "({$channel->getSizeFormatted()} GB) 💾" : ' 🌐' ?><?= $channel->isRefreshing() ? ' <span class="spinner"/>' : ' ✅' ?></span>
            </span>
        </summary>
        <p>
            <span>Aktualisiert: <?= $channel->getLastUpdate() ?></span>
            <span>Videos: <?= $channel->getItemCount() ?> / <?= $channel->getItemLimit() ?></span>
            <span>Laden: <?= $channel->isDownloadEnabled() ? "&checkmark; aktiviert ({$channel->getDownloadedItemCount()} / {$channel->getDownloadedItemLimit()})" : '&cross; deaktivert' ?></span>
        </p>
        <p>
            <a href="/settings/<?= $channel->getId() ?>">&rightarrow; Einstellungen</a>
        </p>
    </details>
<?php endforeach; ?>
<h2>Warteschlange</h2>
<ol>
    <?php foreach ($refreshQueue as $channel): ?>
        <li><?= $channel->getTitle() ?> <small>(Aktualisiert: <?= $channel->getLastUpdate() ?>)</small></li>
    <?php endforeach; ?>
</ol>
</body>
</html>

