<?php

declare(strict_types=1);

require_once "Downloader.class.php";
require_once "ImageConverter.class.php";
require_once "Channel.class.php";
require_once "Item.class.php";
require_once "Rss.class.php";

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

                if (!$downloader->done("$channelId.img")) {
                    unset($data['avatarUrl']);
                }
            }

            $channel->setCover($data['avatarUrl'] ?? "https://$this->ownHost/logo.jpg");
            $channel->setDescription($data['description'] ?? '');
            $channel->setLanguage('en');
            $channel->setFrontend("https://$this->frontendHost/channel/$channelId");
            $channel->setItems(implode(array_map('strval', $this->items($data['relatedStreams']))));
            return $channel;
        }

        return null;
    }

    private function formatCount($value): string
    {
        $value = (int)$value;
        if ($value > 1000000) {
            return number_format(round($value / 1000000, 1), 1, ',', '.') . ' Mio.';
        } else {
            return number_format(round($value, 1), 0, ',', '.');
        }
    }

    public function items(array $videos): array
    {
        $downloader = new Downloader();

        $items = [];
        $limit = 3;
        foreach ($videos as $video) {
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
            $videoFilename = "$videoId.mp4";
            if (count($items) >= $limit) {
                $downloader->delete($videoFilename);
                continue;
            }

            $streamData = $this->stream($videoId);

            if (empty($streamData['fileInfo'])) {
                continue;
            }
            $fileInfo = $streamData['fileInfo'];

            $id = basename($video['uploaderUrl'] ?? '');
            $uploaderFeed = 'https://' . $this->ownHost . "/channel/$id";

            $uploaderName = $video['uploaderName'] ?? '';
            $views = $this->formatCount($streamData['views'] ?? 0);
            $likes = $this->formatCount($streamData['likes'] ?? 0);
            $subscribers = $this->formatCount($streamData['uploaderSubscriberCount'] ?? 0);

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
            $item->setChaptersUrl("https://$this->ownHost/chapters/$videoId.json");
            $item->setUploaderName($uploaderName);
            if ($video['uploaded'] > 0) {
                $item->setDate(date(DATE_RFC2822, intval($video['uploaded'] / 1000)));
            } else {
                $date = new DateTime($streamData['uploadDate']);
                $item->setDate($date->format(DATE_RFC2822));
            }

            $item->setUrl("https://$this->frontendHost{$video['url']}");
            $item->setVideoId($videoId);
            $item->setVideoUrl( "https://$this->ownHost" . $downloader->schedule($fileInfo['url'], $videoFilename));
            if (!$downloader->done($videoFilename)) {
                $item->setVideoUrl($fileInfo['url']);
            }
            $item->setSize((string)$downloader->size($videoFilename));
            $item->setMimeType($fileInfo['mimeType'] ?? 'video/mp4');

            $items[] = $item;
        }

        return $items;
    }

    private function stream(string $videoId): ?array
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