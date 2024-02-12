<?php

declare(strict_types=1);

require_once "Log.class.php";

class Downloader
{
    private Log $log;

    public function __construct(private string $base = __DIR__, private string $folder = DIRECTORY_SEPARATOR . 'static')
    {
        if (!is_dir($this->base . $this->folder)) {
            mkdir($this->base . $this->folder);
        }
        $this->log = new Log();
    }

    public function schedule(string $url, string $filename, string $info = ''): string
    {
        $file = $this->path($filename);

        if (!$this->scheduled($filename)) {
            $info = "$file: $info";
            file_put_contents($this->pathAbsolute($filename) . '.download', json_encode([
                'url' => $url,
                'file' => $file,
                'info' => $info
            ]));
            $this->log->append("scheduled: $info");
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
        $file = $this->pathAbsolute($filename);
        @unlink("$file.download");
        @unlink("$file.lock");
        if (file_exists($file)) {
            @unlink($file);
            $this->log->append("delete: " . $filename);
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
            $errors = $download['errors'] ?? 0;
            $info = $download['info'] ?? $download['file'];
            $file = __DIR__ . $download['file'];

            $lockFile = $file . '.lock';

            try {
                if (file_exists($lockFile)) {
                    $fileTime = filemtime($lockFile);
                    $age = time() - $fileTime;
                    if ($age > 1800 || !file_exists($file)) {
                        @unlink($lockFile);
                        if (file_exists($file)) {
                            @unlink($file);
                        }
                        $this->log->append("unlocked ($age s): $info");
                    } else {
                        $this->log->append("locked ($age s): $info");
                        continue;
                    }
                }

                touch($lockFile);

                if (file_exists($file)) {
                    @unlink($file);
                }

                $this->log->append("downloading: $info");

                $fp = fopen($file, 'w+');

                $ch = curl_init();

                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'],
                    CURLOPT_FILE => $fp,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                ]);

                curl_exec($ch);
                curl_close($ch);

                $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
                $size = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);

                if (
                    200 == $status
                    && ($contentLength === $size || intval($contentLength) === -1 && $size > 0)
                ) {
                    fclose($fp);
                    @unlink($downloadFile);
                    @unlink($lockFile);
                    $this->log->append("finished: $info");
                } else {
                    $this->log->append("ERROR $status, $size B / $contentLength B: $info");
                    fclose($fp);
                    @unlink($file);
                    @unlink($lockFile);
                    if ($errors > 10 || $status === 403 || $status === 404) {
                        @unlink($downloadFile);
                    } else {
                        $download['errors'] = $errors+1;
                        file_put_contents($downloadFile, json_encode($download));
                    }
                }
            } catch (Throwable $exception) {
                $this->log->append($exception->getMessage());
                @unlink($file);
            }
            if (file_exists($lockFile)) {
                @unlink($lockFile);
            }
        }
    }
}