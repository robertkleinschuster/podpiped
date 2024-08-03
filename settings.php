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

$settings = new Settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['limit'])) {
        $settings->setLimit($channelId, (int)$_POST['limit']);
    }
    if (isset($_POST['download_limit'])) {
        $settings->setDownloadLimit($channelId, (int)$_POST['download_limit']);
    }
    if (isset($_POST['download']) && $_POST['download'] === '1') {
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
    <title>Einstellungen</title>
</head>
<body>
<form method="post">
    <p>
        <label>
            Angezeigte Videos
            <input type="number" name="limit" value="<?= $settings->getLimit($channelId) ?>">
        </label>
    </p>
    <p>
        <label>
            Videos Herunterladen
            <input type="checkbox" name="download"
                   value="1"<?= $settings->isDownloadEnabled($channelId) ? ' checked' : '' ?>>
        </label>
        <label id="download_limit">
            Anzahl
            <input type="number" name="download_limit" value="<?= $settings->getDownloadLimit($channelId) ?>">
        </label>
    </p>
    <p>
        <button type="submit">Speichern</button>
    </p>
</form>
</body>
</html>
