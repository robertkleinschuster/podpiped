<?php

declare(strict_types=1);

const API = 'pipedapi.kavin.rocks';
const PROXY = 'pipedproxy.kavin.rocks';

const API_FALLBACK = 'api.piped.yt';
const PROXY_FALLBACK = 'proxy.piped.yt';

const DEFAULT_LIMIT = 50;
const DEFAULT_QUALITY = '720p';
const DEFAULT_MODE = 'subscriptions';
const USE_APCU = false;
const SUGGESTIONS = 2;
const SUGGESTIONS_SOURCE_LIMIT = 2;

const SHORTCUT_LINK = 'https://www.icloud.com/shortcuts/4d649e5897a743b9b221dcd5c0e28903';
const SHORTCUT_FILE = '/Podcast aus YoutTube-Link V2.1.shortcut';

const PATH_CHANNEL = '/channel';
const PATH_PLAYLIST = '/playlist';
const PATH_OPML = '/opml';
const PATH_SUGGESTIONS = '/suggestions';
const PATH_SHORTCUT = '/shortcut';
const PATH_THUMB = '/thumb';
const PATH_CHAPTERS = '/chapters';

set_time_limit(60);
spl_autoload_register('classes');

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

    if (strpos($path, PATH_THUMB) === 0) {
        $proxy = $get['proxy'] ?? PROXY;

        if (!resource_exists("https://$proxy")) {
            $proxy = PROXY_FALLBACK;
        }

        if (isset($get['file'])) {
            $url = $get['file'];
        } else {
            $videoId = $get['video'] ?? basename($path, '.jpg');
            $url = "https://$proxy/vi/$videoId/maxresdefault.jpg?host=i.ytimg.com";
            if (!resource_exists($url)) {
                $url = "https://$proxy/vi/$videoId/hqdefault.jpg?host=i.ytimg.com";
            }
        }

        output_thumbnail($proxy, $url);
        return;
    }

    $api = 'https://' . ($get['api'] ?? API);
    if (!resource_exists($api)) {
        $api = 'https://' . API_FALLBACK;
    }

    if (strpos($path, PATH_CHAPTERS) === 0) {
        $videoId = $get['video'] ?? basename($path, '.json');
        output_chapters($api, $videoId);
        return;
    }

    $frontend = 'https://' . ($get['frontend'] ?? "piped.kavin.rocks");
    $format = $get['format'] ?? 'MPEG_4';
    $quality = $get['quality'] ?? DEFAULT_QUALITY;
    $limit = (int)($get['limit'] ?? DEFAULT_LIMIT);

    if (strpos($path, PATH_PLAYLIST) === 0) {
        $playlistId = $get['id'] ?? basename($path);
        output_playlist($playlistId, $limit, $api, $format, $quality, $frontend, 'subscriptions');
        return;
    }

    if (strpos($path, PATH_CHANNEL) === 0) {
        $channelId = $get['id'] ?? basename($path);
        output_channel($channelId, $limit, $api, $format, $quality, $frontend, 'subscriptions');
        return;
    }

    if (strpos($path, PATH_OPML) === 0) {
        $authToken = $get['authToken'] ?? basename($path);
        output_opml($authToken, $api, $frontend);
        return;
    }

    if (strpos($path, PATH_SHORTCUT) === 0) {
        handle_shortcut($api, $path, $_GET['version'] ?? '1', $_GET['payload'] ?? null);
        return;
    }

    if (!trim($path, '/')) {
        output_help();
        return;
    }

    if (strpos($path, PATH_SUGGESTIONS) === 0) {
        $authToken = $get['authToken'] ?? basename($path);
        output_suggestions($authToken, $api, SUGGESTIONS_SOURCE_LIMIT, $format, $quality, $frontend);
        return;
    }

    output_feed($path, $api, $get);
}

function output_suggestions(
    string $authToken,
    string $api,
    int    $limit,
    string $format,
    string $quality,
    string $frontend
)
{
    $subscriptions = array_column(fetch("$api/subscriptions", ["Authorization: $authToken"]), 'url');
    $feed = fetch("$api/feed?authToken=$authToken");
    $items = [];
    foreach ($feed as $video) {
        parse_str(parse_url($video['url'], PHP_URL_QUERY), $params);
        if (isset($params['v'])) {
            $videoId = $params['v'];
            $streams = fetch("$api/streams/$videoId");
            $related = $streams['relatedStreams'];
            $count = 0;
            foreach ($related as $item) {
                if (!in_array($item['uploaderUrl'], $subscriptions)) {
                    $items[] = $item;
                    $count++;
                }
                if ($count > SUGGESTIONS) {
                    break;
                }
            }
        }
        if (count($items) >= $limit) {
            break;
        }
    }
    if (empty($items)) {
        http_response_code(404);
        return;
    }

    header('content-type: application/xml');
    flush();

    $channel = new Channel();
    $channel->setLanguage('en');
    $channel->setTitle('YouTube Empfehlungen');
    $channel->setDescription('YouTube RSS-Podcast von ' . $frontend);
    $channel->setCopyright($get['copyright'] ?? '&copy; YouTube');
    $channel->setCover(url('/feed.jpg'));
    $channel->setFrontend(url('/', $frontend));
    $channel->setFeedUrl($api);
    $channel->setItems(fetch_items($items, $limit, $api, $format, $quality, $frontend, 'subscriptions'));

    echo new Rss($channel);
}

function handle_shortcut(string $api, string $path, string $version, string $payload = null)
{
    header('Content-Type: application/json');

    if (!$payload) {
        echo json_encode([]);
        return;
    }

    if (strpos($payload, ':') !== false) {
        [$mode, $id] = explode(':', $payload);
        if ($mode === 'channel_by_video') {
            $data = fetch(url("/streams/$id", $api));
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
            $data = fetch("$api/subscriptions", ["Authorization: $id"]);
            $podcasts = [];
            foreach ($data as $datum) {
                if (is_array($datum)) {
                    $id = basename($datum['url']);
                    $podcasts[] = url(PATH_CHANNEL . "/$id");
                }
            }
            echo json_encode(['podcast_list' => $podcasts]);
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

function output_opml(string $authToken, string $api, string $frontend): void
{
    $data = fetch("$api/subscriptions", ["Authorization: $authToken"]);

    header('content-type: application/xml');

    echo <<<EOL
<?xml version="1.0"?>
<opml version="1.0">
    <head>
        <title>YouTube Subscriptions</title>
    </head>
    <body>
EOL;

    foreach ($data as $datum) {
        $title = $datum['name'];
        $id = basename($datum['url']);
        $xmlUrl = url(PATH_CHANNEL . "/$id");
        $htmlUrl = url("/channel/$id", $frontend);

        echo <<<XML
        <outline type="rss" title="$title" xmlUrl="$xmlUrl" htmlUrl="$htmlUrl"/>
XML;
    }

    echo <<<EOL
    </body>
</opml>
EOL;
}

function output_playlist(
    string $playlistId,
    int    $limit,
    string $api,
    string $format,
    string $quality,
    string $frontend,
    string $mode
): void
{
    header('content-type: application/xml');
    flush();

    $data = fetch("$api/playlists/$playlistId");
    $channel = new Channel();
    $channel->setTitle($data['uploader'] . ': ' . $data['name']);
    if (isset($data['uploaderAvatar'])) {
        $data['uploaderAvatar'] = str_replace(
            's48-c-k-c0x00ffffff-no-rw',
            's1000-c-k-c0x00ffffff-no-rw',
            $data['uploaderAvatar']
        );
    }
    $channel->setCover($data['uploaderAvatar'] ?? url('/playlist.jpg'));
    $channel->setDescription($data['description'] ?? '');
    $channel->setLanguage('en');
    $channel->setAuthor($data['uploader'] ?? '');
    $channel->setFrontend(url("/playlist?list=$playlistId", $frontend));
    $channel->setItems(fetch_items($data['relatedStreams'], $limit, $api, $format, $quality, $frontend, $mode));

    echo new Rss($channel);
}

function output_channel(
    string $channelId,
    int    $limit,
    string $api,
    string $format,
    string $quality,
    string $frontend,
    string $mode
): void
{
    header('content-type: application/xml');
    flush();

    $data = fetch("$api/channel/$channelId");
    $channel = new Channel();
    $channel->setTitle($data['name']);

    if (isset($data['avatarUrl'])) {
        $data['avatarUrl'] = str_replace(
            's48-c-k-c0x00ffffff-no-rw',
            's1000-c-k-c0x00ffffff-no-rw',
            $data['avatarUrl']
        );
        $data['avatarUrl'] = url(PATH_THUMB . '?file=' . urlencode($data['avatarUrl']));
    }

    $channel->setCover($data['avatarUrl'] ?? url('/logo.jpg'));
    $channel->setDescription($data['description'] ?? '');
    $channel->setLanguage('en');
    $channel->setFrontend(url("/channel/$channelId", $frontend));
    $channel->setItems(fetch_items($data['relatedStreams'], $limit, $api, $format, $quality, $frontend, $mode));

    echo new Rss($channel);
}

function fetch_items(
    array  $videos,
    int    $limit,
    string $api,
    string $format,
    string $quality,
    string $frontend,
    string $mode
): string
{
    static $videoIds = [];

    $items = '';
    foreach ($videos as $video) {
        if (count($videoIds) + 1 > $limit) {
            break;
        }
        if ($video['type'] !== 'stream') {
            continue;
        }
        $isShort = (bool)($video['isShort'] ?? false);
        if ($isShort && $mode !== 'shorts' || $mode === 'shorts' && !$isShort) {
            continue;
        }
        if (isset($video['url'])) {
            parse_str(parse_url($video['url'], PHP_URL_QUERY), $params);
            if (isset($params['v'])) {
                $videoId = $params['v'];
                if (isset($videoIds[$videoId])) {
                    continue;
                }
                $streamUrl = "/streams/$videoId";
                if (USE_APCU && function_exists('apcu_entry')) {
                    $streamData = apcu_entry($api . $streamUrl, fn() => fetch($api . $streamUrl));
                    $fileInfo = apcu_entry(
                        $videoId . $format . $quality,
                        fn() => find_video_file($streamData, $format, $quality),
                        60
                    );
                } else {
                    $streamData = fetch($api . $streamUrl);
                    $fileInfo = find_video_file($streamData, $format, $quality);
                }

                if (empty($fileInfo)) {
                    continue;
                }
                if ($isShort) {
                    $episodeType = 'trailer';
                } elseif ($mode === 'suggestion_items') {
                    $episodeType = 'bonus';
                } else {
                    $episodeType = 'full';
                }

                $id = basename($video['uploaderUrl'] ?? '');
                $uploaderFeed = url(PATH_CHANNEL . "/$id");

                $uploaderName = $video['uploaderName'] ?? '';
                $views = format_count($streamData['views'] ?? 0);
                $likes = format_count($streamData['likes'] ?? 0);
                $subscribers = format_count($streamData['uploaderSubscriberCount'] ?? 0);

                $item = new Item();
                $item->setTitle($video['title']);
                $item->setHls($streamData['hls'] ?? '');
                $item->setEpisodeType($episodeType);
                $item->setSummary(
                    "👤$uploaderName<br>$subscribers&nbsp;Abos | $views&nbsp;Aufr.&nbsp; | $likes&nbsp;Likes"
                );
                $item->setUploaderUrl(url($video['uploaderUrl'], $frontend));
                $item->setUploaderFeedUrl($uploaderFeed);
                $item->setDescription($streamData['description']);
                $item->setThumbnail(url(PATH_THUMB . "/$videoId.jpg"));
                $item->setDuration((string)(int)$video['duration']);
                $item->setChaptersUrl(url(PATH_CHAPTERS . "/$videoId.json"));
                $item->setUploaderName($uploaderName);
                if ($video['uploaded'] > 0) {
                    $item->setDate(date(DATE_RFC2822, intval($video['uploaded'] / 1000)));
                } else {
                    $date = new DateTime($streamData['uploadDate']);
                    $item->setDate($date->format(DATE_RFC2822));
                }

                $item->setUrl($frontend . $video['url']);
                $item->setVideoUrl($fileInfo['url']);
                $item->setVideoId($videoId);
                $item->setSize((string)intval((int)$video['duration'] * (int)$fileInfo['bitrate'] / 8));
                $item->setMimeType($fileInfo['mimeType'] ?? 'video/mp4');

                $videoIds[$videoId] = true;
                $items .= $item;
            }
        }
    }
    return $items;
}

function find_video_file(array $streamData, string $format, string $quality): array
{
    $videoStreams = $streamData['videoStreams'] ?? [];

    $isValid = fn(
        array $videoStream
    ) => isset($videoStream['videoOnly'], $videoStream['format'], $videoStream['quality'], $videoStream['url'])
        && !$videoStream['videoOnly']
        && $format === $videoStream['format']
        && $quality === $videoStream['quality'];

    foreach ($videoStreams as $videoStream) {
        if ($isValid($videoStream)) {
            return $videoStream;
        }
    }

    return [];
}

function fetch(string $url, array $header = null): array
{
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_URL => $url,
        CURLOPT_SSH_COMPRESSION => true,
        CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'],
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    if ($header) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    }

    $response = curl_exec($ch);
    curl_close($ch);

    if (is_string($response)) {
        $data = @json_decode($response, true);
        if (is_array($data)) {
            return $data;
        }
    }
    return [];
}

function url(string $path, string $host = null): string
{
    $host = $host ?? 'http://' . $_SERVER['HTTP_HOST'];
    $path = ltrim($path, '/');
    return "$host/$path";
}

function format_count($value): string
{
    $value = (int)$value;
    if ($value > 1000000) {
        return number_format(round($value / 1000000, 1), 1, ',', '.') . ' Mio.';
    } else {
        return number_format(round($value, 1), 0, ',', '.');
    }
}

function output_chapters(string $api, string $videoId)
{
    $streamUrl = "/streams/$videoId";

    $streamData = fetch($api . $streamUrl);
    $chapters = [
        'version' => "1.2.0",
        'chapters' => array_map(
            fn(array $chapter) => [
                'title' => $chapter['title'],
                'img' => $chapter['image'],
                'startTime' => $chapter['start']
            ],
            $streamData['chapters']
        ),
    ];

    header('content-type: application/json+chapters');
    echo json_encode($chapters);
}

function output_thumbnail(string $proxy, string $url)
{
    $source_image = imagecreatefromwebp($url);

    $source_imagex = imagesx($source_image);
    $source_imagey = imagesy($source_image);

    $dest_imagex = 1400;
    $dest_imagey = intval($dest_imagex * ($source_imagey / $source_imagex));

    $dest_image = imagecreatetruecolor(1400, 1400);
    imagecopyresampled(
        $dest_image,
        $source_image,
        0,
        intval(700 - $dest_imagey / 2),
        0,
        0,
        $dest_imagex,
        $dest_imagey,
        $source_imagex,
        $source_imagey
    );

    header('Content-Type: image/jpeg');

    imagejpeg($dest_image);
}

function output_feed(
    string $path,
    string $api,
    array  $get,
    array  $modeTitles = [
        'feed' => 'YouTube Feed',
        'shorts' => 'YouTube Shorts',
        'subscriptions' => 'YouTube Abos',
    ]
)
{
    $authToken = $get['authToken'] ?? basename($path) ?: '';
    $channels = $get['channels'] ?? '';
    $mode = $get['mode'] ?? DEFAULT_MODE;
    $frontend = 'https://' . ($get['frontend'] ?? "piped.kavin.rocks");
    $format = $get['format'] ?? 'MPEG_4';
    $quality = $get['quality'] ?? DEFAULT_QUALITY;
    $limit = (int)($get['limit'] ?? DEFAULT_LIMIT);

    $feedUrl = "/feed?authToken=$authToken";
    if (!$authToken) {
        $feedUrl = "/feed/unauthenticated?channels=$channels";
    }

    $channel = new Channel();
    $channel->setLanguage($get['language'] ?? 'en');
    $channel->setTitle($get['title'] ?? $modeTitles[$mode] ?? 'YouTube');
    $channel->setDescription($get['description'] ?? 'YouTube RSS-Podcast von ' . $frontend);
    $channel->setCopyright($get['copyright'] ?? '&copy; YouTube');
    $channel->setCover($get['cover'] ?? url('/feed.jpg'));
    $channel->setFrontend(url('/feed', $frontend));
    $channel->setFeedUrl($api . $feedUrl);
    if (USE_APCU && function_exists('apcu_entry')) {
        $videos = apcu_entry($api . $feedUrl, fn() => fetch($api . $feedUrl), 3600);
    } else {
        $videos = fetch($api . $feedUrl);
    }
    if (empty($videos)) {
        http_response_code(404);
        return;
    }
    header('content-type: application/xml');
    flush();

    $channel->setItems(fetch_items($videos, $limit, $api, $format, $quality, $frontend, $mode));

    echo new Rss($channel);
}

function resource_exists(string $url, int $timeout = 1): bool
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_URL => $url,
        CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'],
        CURLOPT_CONNECTTIMEOUT => $timeout,
    ]);
    return curl_exec($ch) && 200 == curl_getinfo($ch, CURLINFO_HTTP_CODE);
}

function output_help()
{
    $host = $_SERVER['HTTP_HOST'];
    $pathOpml = PATH_OPML;
    $pathPlaylist = PATH_PLAYLIST;
    $pathChannel = PATH_CHANNEL;
    $pathSuggestions = PATH_SUGGESTIONS;
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
  <section>
    <h3>Abos</h3>
    <label>
        <span>Piped Feed URL</span>
        <input type="url" id="feed_url" placeholder="hier einfügen...">   
        <button type="reset" onclick="reset('feed_url')">❌ löschen</button>
    </label>
    <pre id="feed_podcast"></pre>
    <p>Abos Podcast-URL <button onclick="clipboard('feed_podcast')">📋 kopieren</button></p>
    <script>
            handle('feed_url', 'feed_podcast', function(input) {
                  const url = new URL(input);
                  const authToken = url.searchParams.get('authToken');
                  if (!authToken) {
                      return '';
                  }
                  return `http://$host/\${authToken}`;
            })
    </script>
    <hr>
    <h4>OPML</h4>
    <p>Mithilfe des OPML-Feed können alle deine abbonierten Kanäle als dedizierte Podcasts in Apps wie "Pocket Casts" importiert werden.</p>
    <pre id="opml"></pre>
    <p>OPML-URL <button onclick="clipboard('opml')">📋 kopieren</button></p>
    <script>
            handle('feed_url', 'opml', function(input) {
                  const url = new URL(input);
                  const authToken = url.searchParams.get('authToken');
                  if (!authToken) {
                      return '';
                  }
                  return `http://$host$pathOpml/\${authToken}`;
            })
    </script>
    <hr>
    <h4>Empfehlungen</h4>
    <p>Podcast mit Empfehlungen zu den von dir abbonierten Kanälen.</p>
    <pre id="feed_podcast_suggestions"></pre>
    <p>Empfehlungen Podcast-URL <button onclick="clipboard('feed_podcast_suggestions')">📋 kopieren</button></p>
    <script>
            handle('feed_url', 'feed_podcast_suggestions', function(input) {
                  const url = new URL(input);
                  const authToken = url.searchParams.get('authToken');
                  if (!authToken) {
                      return '';
                  }
                  return `http://$host$pathSuggestions/\${authToken}`;
            })
    </script>
  </section>
</body>
</html>
HTML;
}

function classes(string $class): bool
{
    if ($class === Rss::class) {
        class Rss
        {
            private Channel $channel;

            /**
             * @param Channel $channel
             */
            public function __construct(Channel $channel)
            {
                $this->channel = $channel;
            }

            public function __toString(): string
            {
                return <<<XML
<?xml version="1.0" encoding="UTF-8" ?>
 <rss version="2.0" 
 xmlns:podcast="https://podcastindex.org/namespace/1.0" 
 xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" 
 xmlns:content="http://purl.org/rss/1.0/modules/content/">
  $this->channel   
 </rss>
XML;
            }
        }
    }
    if ($class == Item::class) {
        class Item
        {
            private string $title = '';
            private string $episodeType = '';
            private string $summary = '';
            private string $uploaderUrl = '';
            private string $description = '';
            private string $thumbnail = '';
            private string $duration = '';
            private string $chaptersUrl = '';
            private string $uploaderName = '';
            private string $uploaderFeedUrl = '';
            private string $date = '';
            private string $videoId = '';
            private string $videoUrl = '';
            private string $size = '';
            private string $mimeType;
            private string $url = '';
            private string $hls = '';

            /**
             * @param string $title
             * @return Item
             */
            public function setTitle(string $title): Item
            {
                $this->title = htmlentities($title);
                return $this;
            }

            /**
             * @param string $hls
             * @return Item
             */
            public function setHls(string $hls): Item
            {
                $this->hls = $hls;
                return $this;
            }


            /**
             * @param string $episodeType
             * @return Item
             */
            public function setEpisodeType(string $episodeType): Item
            {
                $this->episodeType = $episodeType;
                return $this;
            }

            /**
             * @param string $uploaderFeedUrl
             * @return Item
             */
            public function setUploaderFeedUrl(string $uploaderFeedUrl): Item
            {
                $this->uploaderFeedUrl = htmlentities($uploaderFeedUrl);
                return $this;
            }


            /**
             * @param string $summary
             * @return Item
             */
            public function setSummary(string $summary): Item
            {
                $this->summary = $summary;
                return $this;
            }

            /**
             * @param string $uploaderUrl
             * @return Item
             */
            public function setUploaderUrl(string $uploaderUrl): Item
            {
                $this->uploaderUrl = htmlentities($uploaderUrl);
                return $this;
            }

            /**
             * @param string $description
             * @return Item
             */
            public function setDescription(string $description): Item
            {
                $this->description = $description;
                return $this;
            }

            /**
             * @param string $thumbnail
             * @return Item
             */
            public function setThumbnail(string $thumbnail): Item
            {
                $this->thumbnail = htmlentities($thumbnail);
                return $this;
            }

            /**
             * @param string $duration
             * @return Item
             */
            public function setDuration(string $duration): Item
            {
                $this->duration = $duration;
                return $this;
            }

            /**
             * @param string $chaptersUrl
             * @return Item
             */
            public function setChaptersUrl(string $chaptersUrl): Item
            {
                $this->chaptersUrl = htmlentities($chaptersUrl);
                return $this;
            }

            /**
             * @param string $uploaderName
             * @return Item
             */
            public function setUploaderName(string $uploaderName): Item
            {
                $this->uploaderName = htmlentities($uploaderName);
                return $this;
            }

            /**
             * @param string $date
             * @return Item
             */
            public function setDate(string $date): Item
            {
                $this->date = $date;
                return $this;
            }

            /**
             * @param string $url
             * @return Item
             */
            public function setUrl(string $url): Item
            {
                $this->url = htmlentities($url);
                return $this;
            }


            /**
             * @param string $videoId
             * @return Item
             */
            public function setVideoId(string $videoId): Item
            {
                $this->videoId = $videoId;
                return $this;
            }

            /**
             * @param string $videoUrl
             * @return Item
             */
            public function setVideoUrl(string $videoUrl): Item
            {
                $this->videoUrl = htmlentities($videoUrl);
                return $this;
            }

            /**
             * @param string $size
             * @return Item
             */
            public function setSize(string $size): Item
            {
                $this->size = $size;
                return $this;
            }

            /**
             * @param string $mimeType
             * @return Item
             */
            public function setMimeType(string $mimeType): Item
            {
                $this->mimeType = $mimeType;
                return $this;
            }

            public function __toString()
            {
                return <<<XML
<item>
    <title><![CDATA[$this->title]]></title>   
    <itunes:episodeType>$this->episodeType</itunes:episodeType>
    <itunes:summary><![CDATA[$this->summary]]></itunes:summary>  
    <description><![CDATA[<center>$this->summary
    <br><a href="$this->uploaderUrl">zum Kanal</a><br>
    <a href="$this->uploaderFeedUrl">Kanal Podcast-URL</a>
    <br>
    $this->uploaderFeedUrl
    <br>
    ＿＿＿＿＿＿＿＿＿＿＿＿＿＿<br><br></center>$this->description]]></description>  
    <itunes:image href="$this->thumbnail"/> 
    <itunes:duration>$this->duration</itunes:duration>
    <podcast:chapters url="$this->chaptersUrl" type="application/json+chapters"/>
    <podcast:person><![CDATA[$this->uploaderName]]></podcast:person>
    <pubDate>$this->date</pubDate>
    <link>$this->url</link>
    <guid>$this->videoId</guid>
    <enclosure url="$this->videoUrl" length="$this->size" type="$this->mimeType" />   
    <podcast:alternateEnclosure type="application/x-mpegURL" length="19237" title="HLS">
        <podcast:source uri="$this->hls" />
    </podcast:alternateEnclosure>
</item>
XML;
            }
        }

        return true;
    }
    if ($class == Channel::class) {
        class Channel
        {
            private string $title = '';
            private string $frontend = '';
            private string $language = '';
            private string $description = '';
            private string $feedUrl = '';
            private string $copyright = '';
            private string $cover = '';
            private string $items = '';
            private string $author = '';

            /**
             * @param string $title
             * @return Channel
             */
            public function setTitle(string $title): Channel
            {
                $this->title = $title;
                return $this;
            }

            /**
             * @param string $author
             * @return Channel
             */
            public function setAuthor(string $author): Channel
            {
                $this->author = htmlentities($author);
                return $this;
            }


            /**
             * @param string $frontend
             * @return Channel
             */
            public function setFrontend(string $frontend): Channel
            {
                $this->frontend = htmlentities($frontend);
                return $this;
            }

            /**
             * @param string $language
             * @return Channel
             */
            public function setLanguage(string $language): Channel
            {
                $this->language = $language;
                return $this;
            }

            /**
             * @param string $description
             * @return Channel
             */
            public function setDescription(string $description): Channel
            {
                $this->description = $description;
                return $this;
            }

            /**
             * @param string $feedUrl
             * @return Channel
             */
            public function setFeedUrl(string $feedUrl): Channel
            {
                $this->feedUrl = $feedUrl;
                return $this;
            }

            /**
             * @param string $copyright
             * @return Channel
             */
            public function setCopyright(string $copyright): Channel
            {
                $this->copyright = $copyright;
                return $this;
            }

            /**
             * @param string $cover
             * @return Channel
             */
            public function setCover(string $cover): Channel
            {
                $this->cover = htmlentities($cover);
                return $this;
            }

            /**
             * @param string $items
             * @return Channel
             */
            public function setItems(string $items): Channel
            {
                $this->items = $items;
                return $this;
            }


            public function __toString()
            {
                return <<<XML
  <channel>
   <title><![CDATA[$this->title]]></title>   
   <link>$this->frontend</link>   
   <language>$this->language</language>   
   <description><![CDATA[$this->description<br>Feed: $this->feedUrl]]></description>   
   <copyright><![CDATA[$this->copyright]]></copyright>
   <itunes:author>$this->author</itunes:author>
   <image>
    <title><![CDATA[$this->title]]></title>
    <url>$this->title</url>
    <link>$this->frontend</link>
   </image>
   <itunes:image href="$this->cover"/>
   <podcast:medium>video</podcast:medium>
   $this->items
  </channel>  
XML;
            }
        }

        return true;
    }
    return false;
}
