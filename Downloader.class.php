<?php

declare(strict_types=1);

class Downloader
{
    private Log $log;

    public function __construct(private string $base = __DIR__, private string $folder = DIRECTORY_SEPARATOR . 'static')
    {
        if (!is_dir($this->base . $this->folder)) {
            mkdir($this->base . $this->folder);
        }
    }

    public function schedule(string $url, string $filename): string
    {
        $file = $this->path($filename);

        if (!$this->scheduled($filename)) {
            file_put_contents($this->pathAbsolute($filename) . '.download', json_encode([
                'url' => $url,
                'file' => $file
            ]));
        }

        return $file;
    }

    public function path(string $filename): string
    {
        return $this->folder . DIRECTORY_SEPARATOR . $filename;
    }

    public function pathAbsolute(string $filename): string
    {
        return $this->base . $this->path($filename);
    }

    public function done(string $filename): bool
    {
        $file = $this->pathAbsolute($filename);
        return file_exists($file) && !file_exists("$file.lock");
    }

    public function size(string $filename): int
    {
        if (!$this->done($filename)) {
            return 0;
        }
        $file = $this->pathAbsolute($filename);
        return filesize($file);
    }

    public function delete(string $filename): void
    {
        if ($this->done($filename)) {
            $file = $this->pathAbsolute($filename);
            unlink($file);
        }
    }

    public function scheduled(string $filename): bool
    {
        $file = $this->pathAbsolute($filename);
        return file_exists($file . '.download');
    }

    public function download(): void
    {
        $downloads = glob($this->base . $this->folder . DIRECTORY_SEPARATOR . '*.download');
        foreach ($downloads as $downloadFile) {
            if (!file_exists($downloadFile)) {
                continue;
            }

            $download = json_decode(file_get_contents($downloadFile), true);
            $url = $download['url'];
            $file = __DIR__ . $download['file'];

            $lockFile = $file . '.lock';

            try {
                if (file_exists($lockFile)) {
                    $fileTime = filemtime($lockFile);
                    $age = time() - $fileTime;
                    if (!file_exists($file)) {
                        @unlink($lockFile);
                        $this->log->append("unlocked ($age): $lockFile");
                    }
                    if ($age > 3600) {
                        @unlink($lockFile);
                        if (file_exists($file)) {
                            @unlink($file);
                        }
                        $this->log->append("unlocked ($age): $lockFile");
                    } else {
                        $this->log->append("locked ($age): $lockFile");
                    }
                    continue;
                }

                touch($lockFile);

                if (file_exists($file)) {
                    $this->log->append("exists: $file");

                    @unlink($file);
                    @unlink($lockFile);
                    continue;
                }

                $this->log->append("downloading: $file");

                $fp = fopen($file, 'w+');

                $ch = curl_init();

                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'],
                    CURLOPT_FILE => $fp,
                    CURLOPT_TIMEOUT => 600,
                    CURLOPT_FOLLOWLOCATION => true,
                ]);

                curl_exec($ch);

                curl_close($ch);
                $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
                $size = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);

                $this->log->append("expected size: $contentLength, downloaded size: $size, status: $status");

                if (
                    200 == $status
                    && ($contentLength === $size || intval($contentLength) === -1 && $size > 0)
                ) {
                    fclose($fp);
                    @unlink($downloadFile);
                    @unlink($lockFile);
                    $this->log->append("success: $file");
                } else {
                    $this->log->append("error: $file");
                    fclose($fp);
                    @unlink($file);
                    if ($status === 403) {
                        @unlink($downloadFile);
                    }
                }
            } catch (Throwable $exception) {
                error_log((string)$exception);
                @unlink($file);
            }
            if (file_exists($lockFile)) {
                @unlink($lockFile);
            }
        }
    }
}