<?php

require_once "Settings.class.php";
require_once "CachedClient.class.php";

$channelId = trim($_GET['id'] ?? '');
$channelId = preg_replace('/[^a-zA-Z0-9_\-.]/', '', $channelId);
locale_set_default('de_AT');
date_default_timezone_set('Europe/Vienna');

if (!$channelId) {
    header('Content-Type: text/plain');
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

$client = new Client($_SERVER['HTTP_HOST']);
$cachedClient = new CachedClient($client);
$channel = $cachedClient->channelInfo($channelId);

if ($channel === null) {
    header('Content-Type: text/plain');
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

$channelName = $channel->getTitle();

$lastUpdate = date('Y-m-d H:i:s', filemtime(__DIR__ . '/channel/' . $channelId));
if (file_exists(__DIR__ . '/channel/' . $channelId . '.new')) {
    $nextUpdate = 'in Bearbeitung...';
} else {
    $nextUpdate = (new DateTime())
        ->setTimestamp(filemtime(__DIR__ . '/channel/' . $channelId))
        ->add(new DateInterval('PT1H'))
        ->format('Y-m-d H:i:s');
}
header('Content-Type: text/html; charset=utf-8');

$settings = new Settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['remove_channel'])) {
        $cachedClient->removeChannel($channelId);
        header('Location: https://' . $_SERVER['HTTP_HOST'] . '/settings.php');
        exit;
    }

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

        a, a:visited {
            color: inherit;
        }

        button.remove {
            background: #d52121;
        }

        input[type=number] {
            width: 2.5rem;
        }

        fieldset {
            border-radius: 8px;
        }

        section {
            display: flex;
            flex-direction: column;
            gap: .5rem;
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
<form method="post">
    <fieldset>
        <legend>Kanal-Einstellungen</legend>
        <p>
            <button class="remove" type="submit" name="remove_channel" value="1">Entfernen</button>
        </p>
        <p>
            <button type="submit" name="refresh_channel" value="1">Aktualisierung starten</button>
        </p>
        <label>
            <input type="checkbox" required>
            Ja ich möchte diese Aktion wirklich durchführen.
        </label>
    </fieldset>
</form>
<section>
    <span>Speicher: <?= $channel->getSizeFormatted() ?> GB</span>
    <span>Videos: <?= $channel->getItemCount() ?> / <?= $channel->getItemLimit() ?></span>
    <?php if ($channel->isDownloadEnabled()): ?>
        <span>Geladen: <?= $channel->getDownloadedItemCount() ?> / <?= $channel->getDownloadedItemLimit() ?></span>
    <?php endif; ?>
    <span>Aktualisiert: <span style="white-space: nowrap"><?= $lastUpdate ?></span></span>
    <span>Nächste Aktualisierung: <span style="white-space: nowrap"><?= $nextUpdate ?></span></span>
</section>

<p>
    <a href="/settings.php">&leftarrow; alle Kanäle</a>
</p>
</body>
</html>