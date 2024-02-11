<?php

declare(strict_types=1);

class Log
{
    public function __construct(private string $file = __DIR__ . DIRECTORY_SEPARATOR . 'log')
    {
        if (!file_exists($this->file)) {
            $this->clear();
        }
    }

    public function append(string $line): void
    {
        if (file_exists($this->file) && filesize($this->file) > 1000) {
            $this->clear();
        }
        file_put_contents($this->file, date('Y-m-d H:i:s') . ": $line\n", FILE_APPEND);
    }

    public function clear(): void
    {
        file_put_contents($this->file, '');
    }
}