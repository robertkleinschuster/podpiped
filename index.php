<?php

declare(strict_types=1);

require_once 'Channel.class.php';
require_once 'Item.class.php';
require_once 'Rss.class.php';
require_once 'Client.class.php';
require_once 'CachedClient.class.php';

global $time;
$time = time();

const API = 'pipedapi.kavin.rocks';
//const API = 'api-piped.mha.fi';
//const API = 'pipedapi.r4fo.com';

#const STREAM_API = 'https://pipedapi.r4fo.com';
const STREAM_API = 'https://pipedapi.kavin.rocks';

const PROXY = 'pipedproxy.kavin.rocks';

const API_FALLBACK = 'api.piped.yt';
const PROXY_FALLBACK = 'proxy.piped.yt';

const DEFAULT_LIMIT = 5;
const TIMEOUT = 30;
const CACHE_TTL = 3600;
const DEFAULT_QUALITY = '720p';
const DEFAULT_MODE = 'subscriptions';
const USE_APCU = false;
const SUGGESTIONS = 2;
const SUGGESTIONS_SOURCE_LIMIT = 2;

const SHORTCUT_LINK = 'https://www.icloud.com/shortcuts/e06ffcf132b4407da80d0b78220574f1';
const SHORTCUT_FILE = '/Podcast aus YouTube-Link.shortcut';

const PATH_CHANNEL = '/channel';
const PATH_PLAYLIST = '/playlist';
const PATH_OPML = '/opml';
const PATH_SUGGESTIONS = '/suggestions';
const PATH_SHORTCUT = '/shortcut';
const PATH_CHAPTERS = '/chapters';

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

    $api = 'https://' . ($get['api'] ?? API);
    if (!resource_exists("$api/trending?region=US")) {
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

    if (strpos($path, PATH_PLAYLIST) === 0) {
        $playlistId = $get['id'] ?? basename($path);
        output_playlist($playlistId, 100, $api, $format, $quality, $frontend, 'subscriptions');
        return;
    }

    if (strpos($path, PATH_CHANNEL) === 0) {
        $channelId = $get['id'] ?? basename($path);
        $client = new Client($_SERVER['HTTP_HOST']);
        $cachedClient = new CachedClient($client);
        $channel = $cachedClient->channel($channelId);
        if ($channel) {
            header('content-type: application/xml');
            echo $channel;
        } else {
            http_response_code(404);
        }
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
        if (check_timeout()) {
            break;
        }
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

    $channel = new Channel();
    $channel->setLanguage('en');
    $channel->setTitle('YouTube Empfehlungen');
    $channel->setDescription('YouTube RSS-Podcast von ' . $frontend);
    $channel->setCopyright($get['copyright'] ?? '&copy; YouTube');
    $channel->setCover(url('/feed.jpg'));
    $channel->setFrontend(url('/', $frontend));
    $channel->setFeedUrl($api);
    $channel->setItems(fetch_items($items, $limit, $api, $format, $quality, $frontend, 'subscriptions'));

    header('content-type: application/xml');
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
                if (check_timeout()) {
                    break;
                }
                if (is_array($datum) && isset($datum['url'])) {
                    $id = basename($datum['url']);
                    if (is_channel_valid($id, $api)) {
                        $podcasts[] = url(PATH_CHANNEL . "/$id");
                    }
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

function is_channel_valid(string $id, string $api): bool
{
    if (USE_APCU) {
        $data = apcu_entry($api . $id, fn() => fetch("$api/channel/$id"), 180000);
    } else {
        $data = fetch("$api/channel/$id");
    }
    if (empty($data['relatedStreams'])) {
        return false;
    }

    if (count($data['relatedStreams']) === 0) {
        return false;
    }

    $count = 0;

    foreach ($data['relatedStreams'] as $stream) {
        if ($stream['type'] === 'stream' && !$stream['isShort']) {
            $count++;
        }
    }

    if ($count == 0) {
        return false;
    }

    return true;
}

function output_opml(string $authToken, string $api, string $frontend): void
{
    $data = fetch("$api/subscriptions", ["Authorization: $authToken"]);

    if (empty($data)) {
        http_response_code(404);
        return;
    }

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
        if (!is_channel_valid($id, $api)) {
            continue;
        }
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
    $data = fetch("$api/playlists/$playlistId");

    if (empty($data)) {
        http_response_code(404);
        return;
    }

    $channel = new Channel();
    $channel->setTitle($data['uploader'] . ': ' . $data['name']);
    if (isset($data['uploaderAvatar'])) {
        $data['uploaderAvatar'] = url_avatar($data['uploaderAvatar']);
    }
    $channel->setCover($data['uploaderAvatar'] ?? url('/playlist.jpg'));
    $channel->setDescription($data['description'] ?? '');
    $channel->setLanguage('en');
    $channel->setAuthor($data['uploader'] ?? '');
    $channel->setFrontend(url("/playlist?list=$playlistId", $frontend));
    $channel->setItems(fetch_items($data['relatedStreams'], $limit, $api, $format, $quality, $frontend, $mode));

    header('content-type: application/xml');
    echo new Rss($channel);
}

function save_channel(string $id)
{
    touch(__DIR__ . '/channel/' . $id . '.channel');
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
    $data = fetch("$api/channel/$channelId");

    if (empty($data)) {
        error_log("Empty channel response $channelId");
        http_response_code(404);
        return;
    }

    $channel = new Channel();
    $channel->setTitle($data['name']);

    if (isset($data['avatarUrl'])) {
        $data['avatarUrl'] = url_avatar($data['avatarUrl']);
    }

    $channel->setCover($data['avatarUrl'] ?? url('/logo.jpg'));
    $channel->setDescription($data['description'] ?? '');
    $channel->setLanguage('en');
    $channel->setFrontend(url("/channel/$channelId", $frontend));
    $channel->setItems(fetch_items($data['relatedStreams'], $limit, STREAM_API, $format, $quality, $frontend, $mode));

    header('content-type: application/xml');
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
        if (check_timeout()) {
            break;
        }
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

                if (USE_APCU) {
                    $streamData = apcu_entry($api . $streamUrl . $format . $quality, fn() => fetch_stream($api, $streamUrl, $format, $quality), CACHE_TTL);
                } else {
                    $streamData = fetch_stream($api, $streamUrl, $format, $quality);
                }

                if (empty($streamData)) {
                    continue;
                }

                $fileInfo = $streamData['fileInfo'];

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
                $item->setVideoUrl(save_video($fileInfo['url']));
                $item->setVideoId($videoId);

                if (isset($fileInfo['contentLength']) && $fileInfo['contentLength'] > 0) {
                    $item->setSize((string)$fileInfo['contentLength']);
                } else {
                    $item->setSize("0");
                }

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

function fetch_stream(string $api, string $streamUrl, string $format, string $quality): ?array
{
    $streamData = fetch($api . $streamUrl);
    if (!is_array($streamData)) {
        return null;
    }
    $fileInfo = find_video_file($streamData, $format, $quality);

    unset($streamData['relatedStreams']);
    unset($streamData['videoStreams']);
    unset($streamData['audioStreams']);
    unset($streamData['previewFrames']);

    $streamData['fileInfo'] = $fileInfo;

    return $streamData;
}

function fetch(string $url, array $header = null): ?array
{
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_URL => $url,
        # CURLOPT_SSH_COMPRESSION => true,
        CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'],
        CURLOPT_CONNECTTIMEOUT => 1,
    ]);

    if ($header) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    }

    $response = curl_exec($ch);
    curl_close($ch);

    if (is_string($response)) {
        $data = @json_decode($response, true);
        if (is_array($data)) {
            if (isset($data['error'])) {
                error_log("Error fetching: $url Message: {$data['error']}");
                return null;
            }
            return $data;
        }
    }
    return null;
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

function thumb_path(string $url): string
{
    return '/thumbs/' . md5($url) . '.png';
}

function save_video(string $url): string
{
    $path = '/videos/' . md5($url) . '.mp4';
    $file = __DIR__ . $path;

    $urlFile = $file . '.url';

    if (file_exists($urlFile)) {
        return $url;
    } elseif (file_exists($file)) {
        return url($path);
    } else {
        file_put_contents($file . '.url', $url);
        return $url;
    }
}

function save_thumbnail(string $url)
{
    try {
        ini_set('memory_limit', '50M');

        $file = __DIR__ . thumb_path($url);

        if (file_exists($file)) {
            return;
        }

        $originalFile = __DIR__ . '/thumbs/' . md5($url) . '.webp';
        if (!file_exists($originalFile)) {
            $originalFilePointer = fopen($originalFile, 'wb');

            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL => url($url, 'https://' . PROXY),
                CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'],
                CURLOPT_CONNECTTIMEOUT => 1,
                CURLOPT_FILE => $originalFilePointer
            ]);

            curl_exec($ch);
            curl_close($ch);

            fclose($originalFilePointer);

            if (200 != curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
                unlink($originalFile);
                return;
            }
        }

        $source_image = imagecreatefromwebp($originalFile);

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

        imagejpeg($dest_image, $file);
    } catch (Throwable $exception) {
        error_log((string)$exception);
    }
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
    $videos = fetch($api . $feedUrl);

    if (empty($videos)) {
        http_response_code(404);
        return;
    }

    $channel->setItems(fetch_items($videos, $limit, $api, $format, $quality, $frontend, $mode));

    header('content-type: application/xml');

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

function url_avatar(string $avatarUrl): string
{
    $url = parse_url($avatarUrl);

    $path = str_replace(
        's48-c-k-c0x00ffffff-no-rw',
        's500-c-k-c0x00ffffff-no-rw',
        $url['path']
    );

    $url = "$path?{$url['query']}";

    save_thumbnail($url);
    return url(thumb_path($url));
}

function check_timeout(): bool
{
    global $time;
    return time() - $time > TIMEOUT - 5;
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
