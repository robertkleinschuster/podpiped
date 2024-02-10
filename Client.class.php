<?php

declare(strict_types=1);

require_once "Downloader.class.php";
require_once "ImageConverter.class.php";

class Client
{
    public function __construct(
        private string $ownHost,
        private string $apiHost = 'pipedapi.kavin.rocks',
        private string $frontendHost = 'piped.kavin.rocks',
        private string $proxyHost = 'pipedproxy.kavin.rocks',
    )
    {
    }

    public function fetch(string $path, array $header = null): ?array
    {
        $path = ltrim($path, '/');
        $url = "https://$this->apiHost/$path";

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
                    error_log("Error fetching: $path Message: {$data['error']}");
                    return null;
                }
                return $data;
            }
        }
        return null;
    }


    public function channel(string $channelId): ?Channel
    {
        $data = $this->fetch("/channel/$channelId");

        if (!empty($data)) {
            $channel = new Channel();
            $channel->setTitle($data['name']);

            if (isset($data['avatarUrl'])) {
                $url = parse_url($data['avatarUrl']);

                $path = $url['path'];

                $downloader = new Downloader();
                $imageConvert = new ImageConverter();
                $data['avatarUrl'] = "https://$this->ownHost" . $imageConvert->schedule(
                        $downloader->schedule("https://$this->proxyHost$path?{$url['query']}", "$channelId.img")
                    );
            }

            $channel->setCover($data['avatarUrl'] ?? url('/logo.jpg'));
            $channel->setDescription($data['description'] ?? '');
            $channel->setLanguage('en');
            $channel->setFrontend("https://$this->frontendHost/channel/$channelId");
            $channel->setItems(implode(array_map('strval', $this->items($data['relatedStreams']))));
            return $channel;
        }

        return null;
    }

    private function items(array $videos): array
    {
        $items = [];
        $count = 0;
        foreach ($videos as $video) {
            if ($count >= 2) {
                break;
            }
            if ($video['type'] !== 'stream') {
                continue;
            }
            $isShort = (bool)($video['isShort'] ?? false);
            if ($isShort) {
                continue;
            }
            if (!isset($video['url'])) {
                continue;
            }
            parse_str(parse_url($video['url'], PHP_URL_QUERY), $params);
            if (!isset($params['v'])) {
                continue;
            }
            $videoId = $params['v'];
            $streamData = $this->stream($videoId);

            if (empty($streamData['fileInfo'])) {
                continue;
            }
            $fileInfo = $streamData['fileInfo'];

            $id = basename($video['uploaderUrl'] ?? '');
            $uploaderFeed = url(PATH_CHANNEL . "/$id");

            $uploaderName = $video['uploaderName'] ?? '';
            $views = format_count($streamData['views'] ?? 0);
            $likes = format_count($streamData['likes'] ?? 0);
            $subscribers = format_count($streamData['uploaderSubscriberCount'] ?? 0);

            $item = new Item();
            $item->setTitle($video['title']);
            $item->setHls($streamData['hls'] ?? '');
            $item->setEpisodeType('full');
            $item->setSummary(
                "👤$uploaderName<br>$subscribers&nbsp;Abos | $views&nbsp;Aufr.&nbsp; | $likes&nbsp;Likes"
            );
            $item->setUploaderUrl("https://$this->frontendHost{$video['uploaderUrl']}");
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

            $item->setUrl("https://$this->frontendHost{$video['url']}");
            $downloader = new Downloader();
            $item->setVideoUrl( "https://$this->ownHost" . $downloader->schedule($fileInfo['url'], "$videoId.mp4"));
            if (!$downloader->done("$videoId.mp4")) {
                $count++;
                continue;
            }
            $item->setVideoId($videoId);

            if (isset($fileInfo['contentLength']) && $fileInfo['contentLength'] > 0) {
                $item->setSize((string)$fileInfo['contentLength']);
            } else {
                $item->setSize("0");
            }

            $item->setMimeType($fileInfo['mimeType'] ?? 'video/mp4');

            $items[] = $item;
            $count++;
        }

        return $items;
    }

    public function stream(string $videoId): ?array
    {
        $streamData = $this->fetch("/streams/" . $videoId);
        if (!is_array($streamData)) {
            return null;
        }

        $videoStreams = $streamData['videoStreams'] ?? [];

        unset($streamData['relatedStreams']);
        unset($streamData['videoStreams']);
        unset($streamData['audioStreams']);
        unset($streamData['previewFrames']);

        $isValid = fn(
            array $videoStream
        ) => isset($videoStream['videoOnly'], $videoStream['format'], $videoStream['quality'], $videoStream['url'])
            && !$videoStream['videoOnly']
            && 'MPEG_4' === $videoStream['format']
            && '720p' === $videoStream['quality'];

        foreach ($videoStreams as $videoStream) {
            if ($isValid($videoStream)) {
                $streamData['fileInfo'] = $videoStream;
                return $streamData;
            }
        }

        return null;

    }
}