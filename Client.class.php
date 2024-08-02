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
        private string $apiHost = 'pipedapi.kavin.rocks', //'pipedapi.kavin.rocks', 'piped-api.lunar.icu'
        private string $frontendHost = 'piped.kavin.rocks',
        private string $proxyHost = 'pipedproxy.kavin.rocks', //'pipedproxy.kavin.rocks', 'piped-proxy.lunar.icu'
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
                    throw new Exception("Error fetching: $path Message: {$data['error']}");
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
            $downloader = new Downloader();
            $imageConvert = new ImageConverter();
            $avatarFilename = $playlistId;
            if ($downloader->done($avatarFilename)) {
                $source = $downloader->path($avatarFilename);
                $data['uploaderAvatar'] = "https://$this->ownHost" . $imageConvert->schedule($source);
            } elseif (isset($data['uploaderAvatar'])) {
                $url = parse_url($data['uploaderAvatar']);
                $path = $url['path'];
                $source = $downloader->schedule("https://$this->proxyHost$path?{$url['query']}", $avatarFilename, $data['name'] ?? '');
                $imageConvert->schedule($source);
                $data['uploaderAvatar'] = null;
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
            $channel->complete = (bool)$complete;
            return $channel;
        }

        return null;
    }

    public function channel(string $channelId, bool $downloadVideos): ?Channel
    {
        $data = $this->fetch("/channel/$channelId");

        if (!empty($data)) {
            $channel = new Channel();
            $channel->setTitle($data['name']);
            $downloader = new Downloader();
            $imageConvert = new ImageConverter();
            $avatarFilename = $channelId;
            if ($downloader->done($avatarFilename)) {
                $source = $downloader->path($avatarFilename);
                $data['avatarUrl'] = "https://$this->ownHost" . $imageConvert->schedule($source);
            } elseif (isset($data['avatarUrl'])) {
                $url = parse_url($data['avatarUrl']);
                $path = $url['path'];
                $source = $downloader->schedule("https://$this->proxyHost$path?{$url['query']}", $avatarFilename, $data['name'] ?? '');
                $imageConvert->schedule($source);
                $data['avatarUrl'] = null;
            }

            $channel->setCover($data['avatarUrl'] ?? "https://$this->ownHost/logo.jpg");
            $channel->setDescription($data['description'] ?? '');
            $channel->setLanguage('en');
            $channel->setFrontend("https://$this->frontendHost/channel/$channelId");
            $channel->setDownloadEnabled($downloadVideos);
            $channel->setToggleDownloadUrl("https://$this->ownHost/download/channel/$channelId");
            $items = $this->items($data['relatedStreams'], 20, $downloadVideos);
            $completeItems = array_filter($items, fn(Item $item) => $item->complete);
            $channel->complete = count($items) === count($completeItems) && isset($data['avatarUrl']);
            if (empty($completeItems)) {
                $completeItems = $items;
            }
            $channel->setItems(implode(array_map('strval', $completeItems)));
            return $channel;
        }

        return null;
    }

    /**
     * @param array $videos
     * @param int $limit
     * @return Item[]
     * @throws Exception
     */
    public function items(array $videos, int $limit = 20, bool $downloadVideos = false): array
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
            $chaptersFile = '/chapters/' . $videoId . '.json';
            if (isset($streamData['chapters']) && is_array($streamData['chapters']) && !file_exists(__DIR__ . $chaptersFile)) {
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
                file_put_contents(__DIR__ . $chaptersFile, $chaptersJson);
            }

            $fileInfo = $streamData['fileInfo'];
            $fileInfo720 = $streamData['fileInfo_720p'] ?? null;

            $channelId = basename($video['uploaderUrl'] ?? '');
            $uploaderFeed = 'https://' . $this->ownHost . "/channel/$channelId";

            $uploaderName = $video['uploaderName'] ?? '';

            $item = new Item();
            $item->setTitle($video['title']);
            $item->setEpisodeType('full');
            $item->setSummary($uploaderName);
            $item->setUploaderUrl("https://$this->frontendHost{$video['uploaderUrl']}");
            $item->setUploaderFeedUrl($uploaderFeed);
            $description = $streamData['description'] ?? '';
            $description = str_replace('www.youtube.com', 'piped.video', $description);
            $item->setDescription($description);
            $duration = (int)$video['duration'];
            $item->setDuration((string)$duration);
            if (isset($chaptersJson)) {
                $item->setChaptersUrl("https://$this->ownHost$chaptersFile");
            }
            $item->setUploaderName($uploaderName);

            $date = new DateTime($streamData['uploadDate']);
            $item->setDate($date->format(DATE_RFC2822));

            $item->setUrl("https://$this->frontendHost{$video['url']}");
            $item->setVideoId($videoId);

            if ($fileInfo720) {
                $item->setVideoUrl($fileInfo720['url']);
                $item->setMimeType($fileInfo720['mimeType'] ?? 'video/mp4');
            } else {
                $item->setVideoUrl($fileInfo['url']);
                $item->setMimeType($fileInfo['mimeType'] ?? 'video/mp4');
            }

            $item->setSize((string)$downloader->size($videoFilename));

            $item->setDownloadEnabled($downloadVideos);
            $item->setToggleDownloadUrl("https://$this->ownHost/download/channel/$channelId");
            if (!$downloadVideos) {
                $item->setComplete(true);
                $downloader->delete($videoFilename);
            } elseif ($downloader->done($videoFilename)) {
                $item->setVideoUrl("https://$this->ownHost" . $downloader->path($videoFilename));
                $item->setMimeType($fileInfo['mimeType'] ?? 'video/mp4');
                $item->setComplete(true);
                $item->setDownloaded(true);
            } else {
                $downloader->schedule($fileInfo['url'], $videoFilename, $video['title'] ?? '', "/channel/$channelId.new");
            }

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
            if ($isValid($videoStream, '360p')) {
                $streamData['fileInfo'] = $videoStream;
            }
            if ($isValid($videoStream, '720p')) {
                $streamData['fileInfo_720p'] = $videoStream;
            }
        }

        if (isset($streamData['fileInfo'])) {
            return $streamData;
        }

        return null;

    }
}
