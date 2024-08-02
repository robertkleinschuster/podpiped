<?php

declare(strict_types=1);

require_once 'Log.class.php';

class ChannelDownload
{
    private Log $log;


    public function __construct(
        private string $folder = __DIR__ . '/download/channel/enabled/',
    )
    {
        if (!is_dir($this->folder)) {
            mkdir($this->folder, 0777, true);
        }

        $this->log = new Log();
    }

    public function enable(string $channelId): void
    {
        touch($this->folder . $channelId);
        $this->log->append("Channel download enabled: $channelId");
    }

    public function disable(string $channelId): void
    {
        unlink($this->folder . $channelId);
        $this->log->append("Channel download disabled: $channelId");
    }

    public function isEnabled(string $channelId): bool
    {
        return file_exists($this->folder . $channelId);
    }

    public function toggle(string $channelId): bool
    {
        if ($this->isEnabled($channelId)) {
            $this->disable($channelId);
            return false;
        }
        $this->enable($channelId);
        return true;
    }
}