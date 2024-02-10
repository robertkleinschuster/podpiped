<?php

declare(strict_types=1);

require_once 'Client.class.php';

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
        if (!file_exists($cacheFile)) {
            return false;
        }
        $age = time() - filemtime($cacheFile);
        return $age < 86400;
    }

    public function channel(string $channelId): ?string
    {
        $cacheFile = $this->folder . $channelId . '.cache';

        if (!$this->isValid($cacheFile)) {
            $channel = $this->client->channel($channelId);
            if ($channel) {
                $rss = new Rss($channel);
                file_put_contents($cacheFile, (string)$rss);
            }
        }
        if (!file_exists($cacheFile)) {
            return null;
        }

        return file_get_contents($cacheFile);
    }

    public function refresh(): void
    {
        $channels = glob($this->folder . '*.cache');
        foreach ($channels as $channel) {
            $channelId = basename($channel, '.cache');
            $this->channel($channelId);
        }
    }
}