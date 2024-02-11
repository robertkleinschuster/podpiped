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
        if (file_exists("$cacheFile.new")) {
            return false;
        }
        if (!file_exists($cacheFile)) {
            touch("$cacheFile.new");
            return false;
        }
        $age = time() - filemtime($cacheFile);
        return $age < 900;
    }

    public function channel(string $channelId): ?string
    {
        $cacheFile = $this->folder . $channelId;

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
        $channels = glob($this->folder . '*');
        foreach ($channels as $channel) {
            if (!str_ends_with($channel, '.new')) {
                $result = $this->channel(basename($channel));
                if ($result) {
                    echo "\nrefreshed: " . $channel;
                }
            }
        }
        $channels = glob($this->folder . '*.new');
        foreach ($channels as $newChannel) {
            $age = time() - filemtime($newChannel);
            if ($age > 3600) {
                unlink($newChannel);
            }
        }
    }
}