<?php

declare(strict_types=1);

require_once 'Client.class.php';
require_once 'Rss.class.php';

class CachedClient
{
    public function __construct(private readonly Client $client, private readonly string $folder = __DIR__ . '/channel/')
    {
        if (!is_dir($this->folder)) {
            mkdir($this->folder);
        }
    }

    private function isValid(string $cacheFile): bool
    {
        if (file_exists("$cacheFile.new")) {
            return false;
        }
        if (!file_exists($cacheFile)) {
            touch("$cacheFile.new");
            return false;
        }
        $age = time() - filemtime($cacheFile);
        return $age < 3600;
    }

    public function channel(string $channelId): ?string
    {
        $cacheFile = $this->folder . $channelId;

        if (!$this->isValid($cacheFile)) {
            $this->load($channelId);
        }

        if (!file_exists($cacheFile)) {
            return null;
        }

        return file_get_contents($cacheFile);
    }

    private function load(string $channelId): bool
    {
        $cacheFile = $this->folder . $channelId;
        $channel = $this->client->channel($channelId);
        if ($channel) {
            if ($channel->complete && file_exists("$cacheFile.new")) {
                unlink("$cacheFile.new");
            }

            $rss = new Rss($channel);
            file_put_contents($cacheFile, (string)$rss);
            return true;
        }
        return false;
    }

    public function refresh(): bool
    {
        $channels = glob($this->folder . '*');
        $complete = true;
        foreach ($channels as $channel) {
            if (!str_ends_with($channel, '.new')) {
                if (!$this->isValid($channel)) {
                    $channelId = basename($channel);
                    if ($this->load($channelId)) {
                        echo "\nrefreshed: " . $channelId;
                        flush();
                    } else {
                        $complete = false;
                    }
                }
            }
        }

        return $complete;
    }
}