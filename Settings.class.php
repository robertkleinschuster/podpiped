<?php

declare(strict_types=1);

require_once 'Log.class.php';

class Settings
{
    private Log $log;

    private const SUFFIX_DOWNLOAD = '.download';
    private const SUFFIX_DOWNLOAD_HQ = '.download_hq';
    private const SUFFIX_DOWNLOAD_LIMIT = '.download_limit';
    private const SUFFIX_LIMIT = '.limit';

    public function __construct(
        private string $folder = __DIR__ . '/settings/channel/',
    )
    {
        if (!is_dir($this->folder)) {
            mkdir($this->folder, 0777, true);
        }

        $this->log = new Log();
    }

    public function enableDownload(string $channelId): void
    {
        touch($this->folder . $this->filterChannelId($channelId) . self::SUFFIX_DOWNLOAD);
        $this->log->append("Channel download enabled: $channelId");
    }

    public function disableDownload(string $channelId): void
    {
        unlink($this->folder . $this->filterChannelId($channelId) . self::SUFFIX_DOWNLOAD);
        $this->log->append("Channel download disabled: $channelId");
    }

    public function isDownloadEnabled(string $channelId): bool
    {
        return file_exists($this->folder . $this->filterChannelId($channelId) . self::SUFFIX_DOWNLOAD);
    }

    public function enableDownloadHq(string $channelId): void
    {
        touch($this->folder . $this->filterChannelId($channelId) . self::SUFFIX_DOWNLOAD_HQ);
        $this->log->append("Channel download hq enabled: $channelId");
    }

    public function disableDownloadHq(string $channelId): void
    {
        unlink($this->folder . $this->filterChannelId($channelId) . self::SUFFIX_DOWNLOAD_HQ);
        $this->log->append("Channel download hq disabled: $channelId");
    }

    public function isDownloadHqEnabled(string $channelId): bool
    {
        return file_exists($this->folder . $this->filterChannelId($channelId) . self::SUFFIX_DOWNLOAD_HQ);
    }

    public function getDownloadLimit(string $channelId): int
    {
        if (file_exists($this->folder . $this->filterChannelId($channelId) . self::SUFFIX_DOWNLOAD_LIMIT)) {
            return (int)file_get_contents($this->folder . $channelId . self::SUFFIX_DOWNLOAD_LIMIT);
        }
        return 0;
    }

    public function getLimit(string $channelId): int
    {
        if (file_exists($this->folder . $this->filterChannelId($channelId) . self::SUFFIX_LIMIT)) {
            return (int)file_get_contents($this->folder . $channelId . self::SUFFIX_LIMIT);
        }
        return 10;
    }

    public function setDownloadLimit(string $channelId, int $limit): void
    {
        file_put_contents($this->folder . $channelId . self::SUFFIX_DOWNLOAD_LIMIT, $limit);
        $this->log->append("Changed channel download limit: $channelId, $limit");
    }

    public function setLimit(string $channelId, int $limit): void
    {
        file_put_contents($this->folder . $this->filterChannelId($channelId) . self::SUFFIX_LIMIT, $limit);
        $this->log->append("Changed channel limit: $channelId, $limit");
    }

    private function filterChannelId(string $channelId): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-.]/', '', $channelId);
    }
}