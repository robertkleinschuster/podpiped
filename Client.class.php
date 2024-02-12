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
        if (!is_dir(__DIR__ . '/chapters')) {
            mkdir(__DIR__ . '/chapters');
        }
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

    /**
     * @throws Exception
     */
    public function playlist(string $playlistId): ?Channel
    {
        $data = $this->fetch("/playlists/$playlistId");

        if (!empty($data)) {
            $channel = new Channel();
            $channel->setTitle($data['name']);

            if (isset($data['uploaderAvatar'])) {
                $url = parse_url($data['uploaderAvatar']);
                $path = $url['path'];
                $downloader = new Downloader();
                $imageConvert = new ImageConverter();
                $avatarFilename = $playlistId;
                if ($downloader->done($avatarFilename)) {
                    $source = $downloader->path($avatarFilename);
                    $data['uploaderAvatar'] = "https://$this->ownHost" . $imageConvert->schedule($source);
                } else {
                    $source = $downloader->schedule("https://$this->proxyHost$path?{$url['query']}", $avatarFilename, $data['name'] ?? '');
                    $imageConvert->schedule($source);
                    $data['uploaderAvatar'] = null;
                }
            }

            $channel->setCover($data['uploaderAvatar'] ?? "https://$this->ownHost/playlist.jpg");
            $channel->setAuthor($data['uploader'] ?? '');
            $channel->setDescription($data['description'] ?? '');
            $channel->setLanguage('en');
            $channel->setFrontend("https://$this->frontendHost/playlist?list=$playlistId");

            $items = $this->items($data['relatedStreams'], 50);

            $channel->setItems(implode(array_map('strval', $items)));
            $complete = null;
            foreach ($items as $item) {
                if ($complete === null) {
                    $complete = $item->complete;
                } else {
                    $complete = $complete && $item->complete;
                }
            }
            $channel->complete = $complete;
            return $channel;
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
                $avatarFilename = $channelId;
                if ($downloader->done($avatarFilename)) {
                    $source = $downloader->path($avatarFilename);
                    $data['avatarUrl'] = "https://$this->ownHost" . $imageConvert->schedule($source);
                } else {
                    $source = $downloader->schedule("https://$this->proxyHost$path?{$url['query']}", $avatarFilename, $data['name'] ?? '');
                    $imageConvert->schedule($source);
                    $data['avatarUrl'] = null;
                }
            }

            $channel->setCover($data['avatarUrl'] ?? "https://$this->ownHost/logo.jpg");
            $channel->setDescription($data['description'] ?? '');
            $channel->setLanguage('en');
            $channel->setFrontend("https://$this->frontendHost/channel/$channelId");
            $items = $this->items($data['relatedStreams']);
            $channel->setItems(implode(array_map('strval', $items)));
            $complete = null;
            foreach ($items as $item) {
                if ($complete === null) {
                    $complete = $item->complete;
                } else {
                    $complete = $complete && $item->complete;
                }
            }
            $channel->complete = $complete && isset($data['avatarUrl']);
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

    /**
     * @param array $videos
     * @param int $limit
     * @return Item[]
     * @throws Exception
     */
    public function items(array $videos, int $limit = 2): array
    {
        $downloader = new Downloader();

        $items = [];
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

            if (is_array($streamData['chapters']) && file_exists(__DIR__ . '/chapters/' . $videoId . '.json')) {
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
                $chaptersJson = json_encode($chapters);
                file_put_contents(__DIR__ . '/chapters/' . $videoId . '.json', $chaptersJson);
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
                $date = new DateTime();
                $date->setTimestamp(intval($video['uploaded'] / 1000));
            } else {
                $date = new DateTime($streamData['uploadDate']);
            }

            $item->setDate($date->format(DATE_RFC2822));

            $item->setUrl("https://$this->frontendHost{$video['url']}");
            $item->setVideoId($videoId);
            if ($downloader->done($videoFilename)) {
                $item->setVideoUrl("https://$this->ownHost" . $downloader->path($videoFilename));
                if (time() - $date->getTimestamp() < 86400) {
                    $limit++;
                }
                $item->setComplete(true);
                $filename = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $video['title']);
                $filename = mb_ereg_replace("([\.]{2,})", '', $filename);
                $filename .= "_$videoFilename";
                $item->setDownloadFilename($filename);
            } else {
                $item->setTitle("⏳ - " . $video['title']);
                $item->setVideoUrl($fileInfo['url']);
                $downloader->schedule($fileInfo['url'], $videoFilename, $video['title'] ?? '');
            }
            $item->setSize((string)$downloader->size($videoFilename));
            $item->setMimeType($fileInfo['mimeType'] ?? 'video/mp4');

            $items[] = $item;
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
            array  $videoStream,
            string $res
        ) => isset($videoStream['videoOnly'], $videoStream['format'], $videoStream['quality'], $videoStream['url'])
            && !$videoStream['videoOnly']
            && 'MPEG_4' === $videoStream['format']
            && $res === $videoStream['quality'];

        foreach ($videoStreams as $videoStream) {
            if ($isValid($videoStream, '720p')) {
                $streamData['fileInfo'] = $videoStream;
                return $streamData;
            }
        }

        foreach ($videoStreams as $videoStream) {
            if ($isValid($videoStream, '360p')) {
                $streamData['fileInfo'] = $videoStream;
                return $streamData;
            }
        }

        return null;

    }
}