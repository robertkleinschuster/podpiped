<?php

declare(strict_types=1);

class Log
{
    public function __construct(
        private string $file = __DIR__ . DIRECTORY_SEPARATOR . 'log',
        private string $errorFile = __DIR__ . DIRECTORY_SEPARATOR . 'error',
    )
    {
        if (!file_exists($this->file)) {
            $this->clear();
        }

        if (!file_exists($this->errorFile)) {
            $this->clearError();
        }
    }

    public function append(string $line): void
    {
        if (file_exists($this->file) && time() - filemtime($this->file) > 600) {
            $this->clear();
        }
        file_put_contents($this->file, date('Y-m-d H:i:s') . ": $line\n", FILE_APPEND);
    }

    public function clear(): void
    {
        file_put_contents($this->file, '');
    }

    public function appendError(string $line): void
    {
        if (file_exists($this->errorFile) && time() - filemtime($this->errorFile) > 600) {
            $this->clearError();
        }
        file_put_contents($this->errorFile, date('Y-m-d H:i:s') . ": $line\n", FILE_APPEND);
    }

    public function clearError(): void
    {
        file_put_contents($this->errorFile, '');
    }

}