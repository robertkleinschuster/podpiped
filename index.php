<?php

declare(strict_types=1);

if (($_GET['clearcache'] ?? '') === '1' || ($_SERVER['HTTP_CACHE_CONTROL'] ?? '') === 'no-cache') {
    opcache_reset();
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (strpos($path, '/thumb') === 0) {
    $videoId = $_GET['video'] ?? basename($path, '.jpg');
    $proxy = $_GET['proxy'] ?? 'pipedproxy.kavin.rocks';

    $url = "https://$proxy/vi/$videoId/maxresdefault.jpg?host=i.ytimg.com";

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
    exit;
}

$api = 'https://' . ($_GET['api'] ?? "pipedapi.kavin.rocks");


if (strpos($path, '/chapters') === 0) {
    $videoId = $_GET['video'] ?? basename($path, '.json');

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
    exit;
}

$authToken = $_GET['authToken'] ?? basename($path) ?: '';
$channels = $_GET['channels'] ?? '';
$mode = $_GET['mode'] ?? 'all';
$frontend = 'https://' . ($_GET['frontend'] ?? "piped.kavin.rocks");
$format = $_GET['format'] ?? 'MPEG_4';
$quality = $_GET['quality'] ?? '720p';
$limit = (int)($_GET['limit'] ?? 300);
$defaultTitles = [
    'all' => 'YouTube',
    'shorts' => 'YouTube Shorts',
    'subscriptions' => 'YouTube Abos',
];
$channelTitle = $_GET['title'] ?? $defaultTitles[$mode] ?? 'YouTube';
$channelDescription = $_GET['description'] ?? 'YouTube RSS-Podcast from ' . $frontend;
$channelCopyright = $_GET['copyright'] ?? '&copy; YouTube';
$channelLanguage = $_GET['language'] ?? 'en';
$channelCover = htmlentities($_GET['cover'] ?? url('/logo.jpg'));

$feedUrl = "/feed?authToken=$authToken";
if (!$authToken) {
    $feedUrl = "/feed/unauthenticated?channels=$channels";
}
date_default_timezone_set('UTC');
header('content-type: application/xml');

$videos = fetch($api . $feedUrl);

$items = fetch_items($videos, $limit, $api, $format, $quality, $frontend, $mode);

$xml = <<<XML
<?xml version="1.0" encoding="UTF-8" ?>
 <rss version="2.0" xmlns:podcast="https://podcastindex.org/namespace/1.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:content="http://purl.org/rss/1.0/modules/content/">
  <channel>
   <title><![CDATA[$channelTitle]]></title>   
   <link>$frontend</link>   
   <language>$channelLanguage</language>   
   <description><![CDATA[$channelDescription<br>Feed: $api$feedUrl]]></description>   
   <copyright><![CDATA[$channelCopyright]]></copyright>   
   <image>
    <title><![CDATA[$channelTitle]]></title>
    <url>$channelCover</url>
    <link>$frontend</link>
   </image>
   <itunes:image href="$channelCover"/>
   <podcast:medium>video</podcast:medium>
   $items
  </channel>   
 </rss>
XML;

echo $xml;

function fetch_items(
    array $videos,
    int $limit,
    string $api,
    string $format,
    string $quality,
    string $frontend,
    string $mode,
    bool $related = false
): string {
    static $videoIds = [];

    $items = '';
    foreach ($videos as $i => $video) {
        if (count($videoIds) + 1 > $limit || $related && $i > 4) {
            break;
        }
        if (isset($video['url'])) {
            parse_str(parse_url($video['url'], PHP_URL_QUERY), $params);
            if (isset($params['v'])) {
                $videoId = $params['v'];
                if (isset($videoIds[$videoId])) {
                    continue;
                }
                $streamUrl = "/streams/$videoId";
                $isShort = (bool)$video['isShort'];

                if ($isShort && $mode !== 'shorts' || $mode === 'shorts' && !$isShort) {
                    continue;
                }

                $streamData = fetch($api . $streamUrl);
                $fileInfo = find_video_file($streamData, $format, $quality);
                if (empty($fileInfo)) {
                    continue;
                }
                $videoIds[$videoId] = true;
                $mimeType = $fileInfo['mimeType'] ?? 'video/mp4';
                $videoUrl = htmlentities($fileInfo['url'] ?? '');
                $bitrate = (int)$fileInfo['bitrate'];
                $duration = (int)$video['duration'];
                $uploaderName = htmlentities($video['uploaderName']);
                $description = $streamData['description'];
                $views = format_count($streamData['views']);
                $likes = format_count($streamData['likes']);
                $uploaderSubscriberCount = format_count($streamData['uploaderSubscriberCount']);

                $uploaderUrl = htmlentities(url($video['uploaderUrl'], $frontend));
                $size = intval($duration * $bitrate / 8);
                $summary = "👤$uploaderName<br>$uploaderSubscriberCount&nbsp;Abos | $views&nbsp;Aufr.&nbsp; | $likes&nbsp;Likes";

                $title = $video['title'];
                if ($isShort) {
                    $episodeType = 'trailer';
                } elseif ($related) {
                    $episodeType = 'bonus';
                } else {
                    $episodeType = 'full';
                }

                $url = htmlentities($frontend . $video['url']);
                $thumbnail = htmlentities(url("/thumb/$videoId.jpg"));
                $date = date(DATE_RFC2822, intval($video['uploaded'] / 1000));

                $chapters = htmlentities(url("/chapters/$videoId.json"));

                $items .= <<<XML
<item>
    <title><![CDATA[$title]]></title>   
    <itunes:episodeType>$episodeType</itunes:episodeType>
    <itunes:summary><![CDATA[$summary]]></itunes:summary>  
    <description><![CDATA[<center>$summary<br><a href="$uploaderUrl">zum Kanal</a><br>＿＿＿＿＿＿＿＿＿＿＿＿＿＿<br><br></center>$description]]></description>  
    <itunes:image href="$thumbnail"/> 
    <itunes:duration>$duration</itunes:duration>
    <podcast:chapters url="$chapters" type="application/json+chapters"/>
    <podcast:person><![CDATA[$uploaderName]]></podcast:person>
    <pubDate>$date</pubDate>
    <link>$url</link>
    <guid>$videoId</guid>
    <enclosure url="$videoUrl" length="$size" type="$mimeType" />   
</item>
XML;
                if (!$related && $mode !== 'subscriptions' && isset($streamData['relatedStreams']) && is_array(
                        $streamData['relatedStreams']
                    )) {
                    $items .= fetch_items(
                        $streamData['relatedStreams'],
                        $limit,
                        $api,
                        $format,
                        $quality,
                        $frontend,
                        $mode,
                        true
                    );
                }
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

function fetch(string $url): array
{
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_URL => $url,
        CURLOPT_SSH_COMPRESSION => true,
        CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'],
    ]);

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
    $value = intval($value);
    if ($value > 1000000) {
        return number_format(round($value / 1000000, 1), 1, ',', '.') . ' Mio.';
    } else {
        return number_format(round($value, 1), 0, ',', '.');
    }
}