<?php

declare(strict_types=1);

require_once 'Client.class.php';
require_once 'Downloader.class.php';
require_once 'Rss.class.php';
require_once 'Log.class.php';
require_once 'Settings.class.php';
require_once 'Path.class.php';
require_once 'DiskSpace.class.php';

class CachedClient
{
    private Log $log;

    public const CHANNEL_FOLDER = __DIR__ . Path::PATH_CHANNEL . '/';
    public const PLAYLIST_FOLDER = __DIR__ . Path::PATH_PLAYLIST . '/';

    public function __construct(
        private Client $client,
        private string $channelFolder = self::CHANNEL_FOLDER,
        private string $playlistFolder = self::PLAYLIST_FOLDER
    )
    {
        if (!is_dir($this->channelFolder)) {
            mkdir($this->channelFolder, 0777, true);
        }
        if (!is_dir($this->playlistFolder)) {
            mkdir($this->playlistFolder, 0777, true);
        }

        $this->log = new Log();
    }

    public function isValid(string $cacheFile, int $ttl): bool
    {
        if (file_exists("$cacheFile.new")) {
            return false;
        }
        if (!file_exists($cacheFile)) {
            return false;
        }
        $age = time() - filemtime($cacheFile);
        return $age < $ttl;
    }

    public function isChannelValid(string $channelId): bool
    {
        return $this->isValid($this->channelFolder . $channelId, 3600 * 6);
    }

    public function refreshChannel(string $channelId): void
    {
        touch($this->channelFolder . $channelId . '.new');
        $this->log->append("Refresh triggered for channel: $channelId");
    }

    public function channel(string $channelId): ?string
    {
        if (!$this->isChannelValid($channelId)) {
            $this->loadChannel($channelId);
        }

        $cacheFile = $this->channelFolder . $channelId;
        if (!file_exists($cacheFile)) {
            return null;
        }

        return file_get_contents($cacheFile);
    }

    public function channelInfo(string $channelId): ?Channel
    {
        $cacheFile = $this->channelFolder . $channelId;
        if (!file_exists($cacheFile)) {
            return null;
        }
        $xml = @simplexml_load_file($cacheFile);
        if (!$xml) {
            return null;
        }
        $settings = new Settings();
        $diskSpace = new DiskSpace();
        $size = 0;
        $downloaded = 0;
        $count = 0;
        foreach ($xml->channel->item as $item) {
            $guid = (string)$item->guid;
            $count++;
            if (file_exists(__DIR__ . '/static/' . $guid . '.mp4')) {
                $downloaded++;
            }
            $size += $diskSpace->getSize(__DIR__ . '/static/' . $guid . '.mp4');
        }

        $channel = new Channel();
        $channel->setTitle((string)$xml->channel->title);
        $channel->setId($channelId);
        $channel->setSize($size);
        $channel->setDownloadedItemCount($downloaded);
        $channel->setDownloadedItemLimit($settings->getDownloadLimit($channelId));
        $channel->setDownloadEnabled($settings->isDownloadEnabled($channelId));
        $channel->setItemCount($count);
        $channel->setItemLimit($settings->getLimit($channelId));
        $channel->setRefreshing(!$this->isChannelValid($channelId));
        $channel->setLastUpdate(date('Y-m-d H:i:s', filemtime($cacheFile)));

        return $channel;
    }

    private function loadChannel(string $channelId): bool
    {
        $cacheFile = $this->channelFolder . $channelId;
        try {
            $settings = new Settings();
            $limit = $settings->getLimit($channelId);
            if (!$limit) {
                touch($cacheFile);
                @unlink("$cacheFile.new");
                return false;
            }
            $downloadEnabled = $settings->isDownloadEnabled($channelId);
            $downloadLimit = $settings->getDownloadLimit($channelId);
            $downloadHq = $settings->isDownloadHqEnabled($channelId);
            $channel = $this->client->channel($channelId, $limit, $downloadEnabled, $downloadLimit, $downloadHq);
            if ($channel) {
                if ($channel->complete) {
                    @unlink("$cacheFile.new");
                }

                $rss = new Rss($channel);
                file_put_contents($cacheFile, (string)$rss);
                return true;
            } else {
                $this->log->append('failed to refresh channel: ' . $channelId);
            }
        } catch (Throwable $exception) {
            if (str_contains($exception->getMessage(), 'This channel does not exist.')) {
                @unlink($cacheFile);
                @unlink("$cacheFile.new");
                $this->log->append('removed channel: ' . $channelId);
            } else {
                throw $exception;
            }
        }

        return false;
    }

    public function playlist(string $playlistId): ?string
    {
        $cacheFile = $this->playlistFolder . $playlistId;

        if (!$this->isValid($cacheFile, 3600)) {
            $this->loadPlaylist($playlistId);
        }

        if (!file_exists($cacheFile)) {
            return null;
        }

        return file_get_contents($cacheFile);
    }

    private function loadPlaylist(string $playlistId): bool
    {
        $cacheFile = $this->playlistFolder . $playlistId;
        try {
            $playlist = $this->client->playlist($playlistId);
            if ($playlist) {
                if ($playlist->complete) {
                    @unlink("$cacheFile.new");
                }

                $rss = new Rss($playlist);
                file_put_contents($cacheFile, (string)$rss);
                return true;
            }
        } catch (Throwable $exception) {
            if (str_contains($exception->getMessage(), 'Playlist not found')) {
                @unlink($cacheFile);
                @unlink("$cacheFile.new");
                $this->log->append('removed playlist: ' . $playlistId);
            } else {
                throw $exception;
            }
        }

        return false;
    }

    public function listChannels(): array
    {
        $files = glob($this->channelFolder . '*');

        usort($files, function ($a, $b) {
            $aTime = filemtime($a);
            $bTime = filemtime($b);
            if (str_ends_with($a, '.new')) {
                $aTime = -filemtime($a);
            }

            if (str_ends_with($b, '.new')) {
                $bTime = -filemtime($b);
            }
            return $aTime <=> $bTime;
        });

        $channels = [];
        foreach ($files as $file) {
            $channelId = basename($file, '.new');
            if (!in_array($channelId, $channels)) {
                $channels[] = $channelId;
            }
        }

        return $channels;
    }

    public function refreshChannels(): void
    {
        try {
            foreach ($this->listChannels() as $channelId) {
                try {
                    if ($this->isChannelValid($channelId)) {
                        $channel = $this->channelInfo($channelId);
                        if (!$channel) {
                            $this->refreshChannel($channelId);
                        }
                        if (
                            $channel->getItemCount() < $channel->getItemLimit()
                            || $channel->isDownloadEnabled() && $channel->getDownloadedItemCount() < $channel->getDownloadedItemLimit()
                        ) {
                            $this->refreshChannel($channelId);
                        }
                    } else {
                        if ($this->loadChannel($channelId)) {
                            $this->log->append("refreshed channel: " . $channelId);
                        }
                        sleep(10);
                    }
                } catch (Throwable $exception) {
                    error_log($exception->getMessage());
                    $this->log->appendError($exception->getMessage());
                    sleep(10);
                }

            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->log->appendError($exception->getMessage());
        }
    }

    public function refreshPlaylists(): bool
    {
        try {
            $files = glob($this->playlistFolder . '*');
            $complete = true;
            foreach ($files as $cacheFile) {
                if (!str_ends_with($cacheFile, '.new')) {
                    if (!$this->isValid($cacheFile, 600)) {
                        $playlistId = basename($cacheFile);
                        if ($this->loadPlaylist($playlistId)) {
                            $this->log->append("refreshed playlist: " . $playlistId);
                        } else {
                            $complete = false;
                        }
                    }
                }
            }

            return $complete;
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->log->appendError($exception->getMessage());
            return true;
        }
    }

    public function removeVideoDownloads(string $channelId): void
    {
        $cacheFile = $this->channelFolder . $channelId;
        $downloader = new Downloader();
        $xml = @simplexml_load_file($cacheFile);
        if ($xml) {
            foreach ($xml->channel->item as $item) {
                $videoId = (string)$item->guid;
                $downloader->delete("$videoId.mp4");
            }
        }
    }

    public function removeChannel(string $channelId): void
    {
        $cacheFile = $this->channelFolder . $channelId;
        $xml = @simplexml_load_file($cacheFile);
        $downloader = new Downloader();
        $downloader->delete($channelId);
        $downloader->delete("$channelId.jpg");
        $channelName = '';
        if ($xml) {
            $channelName = (string)$xml->channel->title;
            foreach ($xml->channel->item as $item) {
                $videoId = (string)$item->guid;
                $downloader->delete("$videoId.mp4");
            }
        }
        @unlink($cacheFile);
        @unlink("$cacheFile.new");
        $this->log->append("removed channel: " . $channelId . ' ' . $channelName);
    }
}