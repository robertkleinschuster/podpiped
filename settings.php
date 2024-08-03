<?php

require_once "Settings.class.php";
require_once "CachedClient.class.php";

$channelId = trim($_GET['id'] ?? '');
$channelId = preg_replace('/[^a-zA-Z0-9_\-.]/', '', $channelId);

if ($channelId) {
    require_once "channel_settings.php";
    exit;
}

$channels = array_filter(
    array_map('basename', glob(__DIR__ . '/channel/*')),
    fn(string $id) => !str_ends_with($id, '.new')
);

sort($channels, SORT_LOCALE_STRING);

$settings = new Settings();

$channels = array_map(function (string $id) use ($settings) {
    $xml = simplexml_load_file(__DIR__ . '/channel/' . $id);
    return [
        'id' => $id,
        'name' => (string)$xml->channel->title,
        'downloadEnabled' => $settings->isDownloadEnabled($id),
        'lastUpdate' => date('Y-m-d H:i:s', filemtime(__DIR__ . '/channel/' . $id))
    ];

}, $channels);

header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client = new Client($_SERVER['HTTP_HOST']);
    $cachedClient = new CachedClient($client);
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
            border-bottom: 1px solid var(--text);
            padding: .5rem;
        }

        details p {
            display: flex;
            flex-direction: column;
            padding-left: 1rem;
        }
    </style>
</head>
<body>
<h1>Einstellungen</h1>
<?php foreach ($channels as $channel): ?>
    <details>
        <summary>
            <a href="/settings/<?= $channel['id'] ?>"><?= $channel['name'] ?></a><?= $channel['downloadEnabled'] ? ' &downarrow;' : '' ?>
        </summary>
        <p>
            <span>Aktualisiert: <?= $channel['lastUpdate'] ?></span>
            <span>Download: <?= $channel['downloadEnabled'] ? '&checkmark; aktiviert' : '&cross; deaktivert' ?></span>
        </p>
    </details>
<?php endforeach; ?>
</body>
</html>

