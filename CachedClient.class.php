<?php

declare(strict_types=1);

require_once 'Client.class.php';
require_once 'Rss.class.php';
require_once 'Log.class.php';

class CachedClient
{
    private Log $log;

    public function __construct(
        private readonly Client $client,
        private readonly string $channelFolder = __DIR__ . '/channel/',
        private readonly string $playlistFolder = __DIR__ . '/playlist/'
    )
    {
        if (!is_dir($this->channelFolder)) {
            mkdir($this->channelFolder);
        }
        if (!is_dir($this->playlistFolder)) {
            mkdir($this->playlistFolder);
        }

        $this->log = new Log();
    }

    private function isValid(string $cacheFile, int $ttl): bool
    {
        if (file_exists("$cacheFile.new")) {
            return false;
        }
        if (!file_exists($cacheFile)) {
            touch("$cacheFile.new");
            return false;
        }
        $age = time() - filemtime($cacheFile);
        return $age < $ttl;
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
        $channel = $this->client->channel($channelId);
        if ($channel) {
            if ($channel->complete && file_exists("$cacheFile.new")) {
                unlink("$cacheFile.new");
            }

            $rss = new Rss($channel);
            file_put_contents($cacheFile, (string)$rss);
            return true;
        }
        unlink("$cacheFile.new");
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
        $playlist = $this->client->playlist($playlistId);
        if ($playlist) {
            if ($playlist->complete && file_exists("$cacheFile.new")) {
                unlink("$cacheFile.new");
            }

            $rss = new Rss($playlist);
            file_put_contents($cacheFile, (string)$rss);
            return true;
        }
        unlink("$cacheFile.new");
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
            $this->log->append($exception->getMessage());
            return true;
        }
    }
}