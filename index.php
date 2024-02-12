<?php

declare(strict_types=1);

require_once 'Channel.class.php';
require_once 'Item.class.php';
require_once 'Rss.class.php';
require_once 'Client.class.php';
require_once 'CachedClient.class.php';

global $time;
$time = time();
const TIMEOUT = 30;
const SHORTCUT_LINK = 'https://www.icloud.com/shortcuts/e06ffcf132b4407da80d0b78220574f1';
const SHORTCUT_FILE = '/Podcast aus YouTube-Link.shortcut';

const PATH_CHANNEL = '/channel';
const PATH_PLAYLIST = '/playlist';
const PATH_OPML = '/opml';
const PATH_SUGGESTIONS = '/suggestions';
const PATH_SHORTCUT = '/shortcut';


set_time_limit(TIMEOUT);
ini_set('max_execution_time', (string)TIMEOUT);
ini_set('memory_limit', '8M');
ini_set('post_max_size', '0');
ini_set('upload_max_filesize', '0');

if (($_GET['clearcache'] ?? '') === '1' || ($_SERVER['HTTP_CACHE_CONTROL'] ?? '') === 'no-cache') {
    opcache_reset();
    ini_set('display_errors', '1');
}

date_default_timezone_set('UTC');

main($_SERVER, $_GET);
exit;
function main(array $server, array $get): void
{
    $path = parse_url($server['REQUEST_URI'], PHP_URL_PATH);

    if (strpos($path, PATH_PLAYLIST) === 0) {
        $playlistId = $get['id'] ?? basename($path);
        $client = new Client($_SERVER['HTTP_HOST']);
        $cachedClient = new CachedClient($client);
        $retry = 0;
        while (!isset($channel) && $retry <= 5) {
            $channel = $cachedClient->playlist($playlistId);
            if ($channel) {
                header('content-type: application/xml');
                echo $channel;
            } else {
                $retry++;
            }
        }

        if (!isset($channel)) {
            http_response_code(404);
        }

        return;
    }

    if (strpos($path, PATH_CHANNEL) === 0) {
        $channelId = $get['id'] ?? basename($path);
        $client = new Client($_SERVER['HTTP_HOST']);
        $cachedClient = new CachedClient($client);
        $retry = 0;
        while (!isset($channel) && $retry <= 5) {
            $channel = $cachedClient->channel($channelId);
            if ($channel) {
                header('content-type: application/xml');
                echo $channel;
            } else {
                $retry++;
            }
        }

        if (!isset($channel)) {
            http_response_code(404);
        }

        return;
    }

    if (strpos($path, PATH_SHORTCUT) === 0) {
        handle_shortcut($_GET['version'] ?? '1', $_GET['payload'] ?? null);
        return;
    }

    if (!trim($path, '/')) {
        output_help();
    }
}

function handle_shortcut(string $version, string $payload = null)
{
    header('Content-Type: application/json');

    if (!$payload) {
        echo json_encode([]);
        return;
    }

    if (strpos($payload, ':') !== false) {
        [$mode, $id] = explode(':', $payload);
        if ($mode === 'channel_by_video') {
            $client = new Client($_SERVER['HTTP_HOST']);
            $data = $client->stream($id);
            $channelId = basename($data['uploaderUrl']);
            echo json_encode(['podcast' => url(PATH_CHANNEL . "/$channelId")]);
            return;
        }
        if ($mode === 'channel') {
            echo json_encode(['podcast' => url(PATH_CHANNEL . "/$id")]);
            return;
        }
        if ($mode === 'playlist') {
            echo json_encode(['podcast' => url(PATH_PLAYLIST . "/$id")]);
            return;
        }
        if ($mode === 'piped_feed_suggestions') {
            echo json_encode(['podcast' => url(PATH_SUGGESTIONS . "/$id")]);
            return;
        }
        if ($mode === 'piped_feed') {
            echo json_encode(['podcast' => url("/$id")]);
            return;
        }
        if ($mode === 'subscriptions') {
            echo json_encode(['podcast_list' => []]);
            return;
        }
    }


    $urlQuery = parse_url($payload, PHP_URL_QUERY);
    $urlParams = [];
    if ($urlQuery) {
        parse_str($urlQuery, $urlParams);
    }

    $urlPath = parse_url($payload, PHP_URL_PATH);

    $menu = [];
    $menu['Abbrechen'] = '';

    if ($urlPath === '/watch' && isset($urlParams['v'])) {
        $menu['Kanal hinzufügen'] = 'channel_by_video:' . $urlParams['v'];
    }

    if ($urlPath === '/feed/unauthenticated/rss' && isset($urlParams['channels'])) {
        $menu['Kanal hinzufügen'] = 'channel:' . $urlParams['channels'];
    }

    if (strpos($urlPath, '/channel') === 0 && basename($urlPath) !== 'channel') {
        $menu['Kanal hinzufügen'] = 'channel:' . basename($urlPath);
    }

    if ($urlPath === '/feed/rss' && isset($urlParams['authToken'])) {
        $menu['Aggregierten Abo-Feed hinzufügen'] = 'piped_feed:' . $urlParams['authToken'];
        $menu['Empfehlungen hinzufügen'] = 'piped_feed_suggestions:' . $urlParams['authToken'];
        if ($version == '2') {
            $menu['Alle abbonierten Kanäle hinzufügen'] = 'subscriptions:' . $urlParams['authToken'];
        }
    }

    if (in_array($urlPath, ['/playlist', '/watch']) && isset($urlParams['list'])) {
        $menu['Playlist hinzufügen '] = 'playlist:' . $urlParams['list'];
    }

    echo json_encode(['menu' => $menu]);
}


function url(string $path, string $host = null): string
{
    $host = $host ?? 'http://' . $_SERVER['HTTP_HOST'];
    $path = ltrim($path, '/');
    return "$host/$path";
}

function output_help()
{
    $host = $_SERVER['HTTP_HOST'];
    $pathPlaylist = PATH_PLAYLIST;
    $pathChannel = PATH_CHANNEL;
    $shortcutIcloud = SHORTCUT_LINK;
    $shortcutFile = SHORTCUT_FILE;
    echo <<<HTML
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="robots" content="noindex,follow" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="msapplication-TileColor" content="#da532c">
    <meta name="theme-color" content="#ffffff">
    <title>PodPiped - YouTube als Podcasts</title>
    <meta name="description" content="Tool zur umwandlung der Feeds von piped.video zu Podcast RSS-Feeds.">
    <meta name="og:title" content="PodPiped - YouTube als Podcasts">
    <meta name="og:image" content="/android-chrome-512x512.png">
    <meta name="og:description" content="Tool zur umwandlung der Feeds von piped.video zu Podcast RSS-Feeds.">
    <style>
        *, *:before, *:after {
            box-sizing: border-box;
        }
        html, body {
           font-family: sans-serif;
           background: #f5f5f5;
           margin: 0;
           padding: 0;
        }  
        header {
            margin: 1rem;
        }
        section {
            padding: 1rem;
            margin: 1rem;
            background: white;
            border-radius: .25rem;
            box-shadow: 0 0 15px rgba(117,117,117,0.5);
        }
        h3 {
            margin-top: 0;
        }
        label {
            display: flex;
            flex-wrap: wrap;
            gap: .25rem;
            align-items: center;
        }
        label span {
            flex: 1 0 100%
        }
        label input {
            flex-grow: 1;
        }
        input {
            padding: .25rem;
            border-radius: .25rem;
            border: 1px solid grey;
            font-size: 14px;
        }
        pre {
            padding: .25rem;
            width: 100%;
            border: 1px solid black;
            overflow: scroll;
        }
        pre:empty {
            display: none;
        }
        pre:empty ~ * {
            display: none;
        }
        button {
            padding: .5rem;
            cursor: pointer;
            border-radius: .25rem;
            border: none;
            background: #0f5590;
            color: white;
            text-decoration: underline;
        }
        button[type=reset] {
            background: none;
            border: 1px solid red;
            color: black;
            padding: .25rem;
            font-size: 12px;
        }
    </style>
    <script>
        function fallbackCopyTextToClipboard(text) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
  
            // Avoid scrolling to bottom
            textArea.style.top = "0";
            textArea.style.left = "0";
            textArea.style.position = "fixed";

            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            document.execCommand('copy')

            document.body.removeChild(textArea);
        }
        function clipboard(id) {
            const elem = document.getElementById(id);
            if (navigator.clipboard) {
                navigator.clipboard.writeText(elem.innerText);
            } else {
                fallbackCopyTextToClipboard(elem.innerText);
            }
        }
        function reset(id) {
            const elem = document.getElementById(id);
            elem.value = '';
            elem.dispatchEvent(new Event('change'));
        }
        function handle(input, output, callback) {
            
            if (typeof input === 'string') {
                input = document.getElementById(input)
            }
             if (typeof output === 'string') {
                output = document.getElementById(output)
            }
            
            input.addEventListener('change', function() {
                if (input.value === '') {
                    output.innerText = '';
                    return;
                }
                if (!input.checkValidity()) {
                    input.reportValidity();
                    return;
                }
                try {
                    output.innerText = callback(input.value);
                } catch(e) {
                    alert(e);
                }
            })
        }
    </script>
</head>
<body>
  <header>
    <h1>PodPiped</h1>
    <h2>YouTube als Podcasts</h2>
    <p>Umwandeln von Piped-URLs in Podcast-URLs.</p>
    <p>Feeds von <a href="https://piped.video/">piped.video</a> werden unterstützt.</p>
  </header>
  <section>
    <h3>Apple Kurzbefehl</h3>
    <a href="$shortcutIcloud">Von iCloud hinzufügen</a>
    <br>
    <br>
    <a href="$shortcutFile">Von Datei hinzufügen</a>
  </section>
  <section>
    <h3>Kanal</h3>
    <label>
        <span>Piped Kanal URL</span>
        <input type="url" id="channel_url" placeholder="hier einfügen...">
        <button type="reset" onclick="reset('channel_url')">❌ löschen</button>
    </label>
    <pre id="channel_podcast"></pre>
    <p>Podcast-URL <button onclick="clipboard('channel_podcast')">📋 kopieren</button></p>
    <script>
            handle('channel_url', 'channel_podcast', function(input) {
                  const url = new URL(input);
                  let id = '';
                  if (url.searchParams.has('channels')) {
                      id = url.searchParams.get('channels');
                  } else {
                      id = url.pathname.split('/').pop();
                  }
                  if (!id) {
                      return '';
                  }
                  return `http://$host$pathChannel/\${id}`;
            })
    </script>
  </section>
  <section>
    <h3>Playlist</h3>
    <label>
        <span>Piped Playlist URL</span>
        <input type="url" id="playlist_url" placeholder="hier einfügen...">   
        <button type="reset" onclick="reset('playlist_url')">❌ löschen</button>
    </label>
    <pre id="playlist_podcast"></pre>
    <p>Podcast-URL <button onclick="clipboard('playlist_podcast')">📋 kopieren</button></p>
    <script>
            handle('playlist_url', 'playlist_podcast', function(input) {
                  const url = new URL(input);
                  let id = '';
                  if (url.searchParams.has('list')) {
                      id = url.searchParams.get('list');
                  } else {
                      id = url.pathname.split('/').pop();
                  }
                  if (!id) {
                      return '';
                  }
                  return `http://$host$pathPlaylist/\${id}`;
            })
    </script>
  </section>
</body>
</html>
HTML;
}
