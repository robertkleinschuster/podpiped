<?php

declare(strict_types=1);

require_once 'Client.class.php';
require_once 'Downloader.class.php';
require_once 'Rss.class.php';
require_once 'Log.class.php';
require_once 'Settings.class.php';
require_once 'Path.class.php';

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

    private function isValid(string $cacheFile, int $ttl): bool
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

    public function refreshChannel(string $channelId): void
    {
        touch($this->channelFolder . $channelId . '.new');
        $this->log->append("Refresh triggered for channel: $channelId");
    }

    public function channel(string $channelId): ?string
    {
        $cacheFile = $this->channelFolder . $channelId;

        if (!$this->isValid($cacheFile, 3600)) {
            $this->loadChannel($channelId);
        }

        if (!file_exists($cacheFile)) {
            return null;
        }

        return file_get_contents($cacheFile);
    }

    private function loadChannel(string $channelId): bool
    {
        $cacheFile = $this->channelFolder . $channelId;
        try {
            $settings = new Settings();
            $limit = $settings->getLimit($channelId);
            $downloadEnabled = $settings->isDownloadEnabled($channelId);
            $downloadLimit = $settings->getDownloadLimit($channelId);
            $channel = $this->client->channel($channelId, $limit, $downloadEnabled, $downloadLimit);
            if ($channel) {
                if ($channel->complete) {
                    @unlink("$cacheFile.new");
                }

                $rss = new Rss($channel);
                file_put_contents($cacheFile, (string)$rss);
                return true;
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

    public function refreshChannels(): bool
    {
        try {

            $files = glob($this->channelFolder . '*');
            $complete = true;
            foreach ($files as $cacheFile) {
                if (!str_ends_with($cacheFile, '.new')) {
                    if (!$this->isValid($cacheFile, 3600)) {
                        $channelId = basename($cacheFile);
                        if ($this->loadChannel($channelId)) {
                            $this->log->append("refreshed channel: " . $channelId);
                        } else {
                            $complete = false;
                        }
                    }
                }
            }

            return $complete;
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->log->append($exception->getMessage());
            return true;
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
            $this->log->append($exception->getMessage());
            return true;
        }
    }

    public function removeChannel(string $channelId): void
    {
        $cacheFile = $this->channelFolder . $channelId;
        $xml = simplexml_load_file($cacheFile);
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