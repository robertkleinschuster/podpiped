<?php

require_once "Settings.class.php";
require_once "CachedClient.class.php";

$channelId = trim($_GET['id'] ?? '');
$channelId = preg_replace('/[^a-zA-Z0-9_\-.]/', '', $channelId);

if (!$channelId) {
    header('Content-Type: text/plain');
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

$xml = simplexml_load_file(__DIR__ . '/channel/' . $channelId);

if ($xml === false) {
    header('Content-Type: text/plain');
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

$channelName = (string)$xml->channel->title;
$lastUpdate = date('Y-m-d H:i:s', filemtime(__DIR__ . '/channel/' . $channelId));

header('Content-Type: text/html; charset=utf-8');

$settings = new Settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['limit'])) {
        $settings->setLimit($channelId, (int)$_POST['limit']);
    }
    if (isset($_POST['download_limit'])) {
        $settings->setDownloadLimit($channelId, (int)$_POST['download_limit']);
    }
    if ($settings->getDownloadLimit($channelId)) {
        $settings->enableDownload($channelId);
    } else {
        $settings->disableDownload($channelId);
    }

    $client = new Client($_SERVER['HTTP_HOST']);
    $cachedClient = new CachedClient($client);
    $cachedClient->refreshChannel($channelId);

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
    <title><?= $channelName ?> - Einstellungen</title>
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

        label {
            display: flex;
            gap: .5rem;
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

        input[type=number] {
            width: 2.5rem;
        }

        fieldset {
            border-radius: 8px;
        }
    </style>
</head>
<body>
<h1><?= $channelName ?></h1>
<form method="post">
    <fieldset>
        <legend>Video-Einstellungen</legend>
        <p>
            <label>
                Anzeigen:
                <input type="number" name="limit" value="<?= $settings->getLimit($channelId) ?>">
            </label>
        </p>
        <p>
            <label>
                Herunterladen:
                <input type="number" name="download_limit" value="<?= $settings->getDownloadLimit($channelId) ?>">
                <?= $settings->isDownloadEnabled($channelId) ? '&check;' : '' ?>
            </label>
        </p>
        <button type="submit">Speichern</button>
    </fieldset>
</form>
<div>Aktualisiert: <span style="white-space: nowrap"><?= $lastUpdate ?></span></div>
</body>
</html>
