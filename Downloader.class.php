<?php

declare(strict_types=1);

class Downloader
{
    public function __construct(private string $base = __DIR__, private string $folder = DIRECTORY_SEPARATOR . 'static')
    {
        if (!is_dir($this->base . $this->folder)) {
            mkdir($this->base . $this->folder);
        }
    }

    public function schedule(string $url, string $ext): string
    {
        $name = $this->folder . DIRECTORY_SEPARATOR . md5($url);
        $file = "$name.$ext";
        file_put_contents($this->base . DIRECTORY_SEPARATOR . $name . '.download', json_encode([
            'url' => $url,
            'file' => $file
        ]));

        return $file;
    }

    public function download(): void
    {
        $downloads = glob($this->base . $this->folder . DIRECTORY_SEPARATOR . '*.download');
        foreach ($downloads as $downloadFile) {
            $download = json_decode(file_get_contents($downloadFile), true);
            $url = $download['url'];
            $file = __DIR__ . $download['file'];

            $lockFile = $file . '.lock';

            try {
                if (file_exists($lockFile)) {
                    $fileTime = filemtime($lockFile);
                    $age = time() - $fileTime;
                    if ($age > 3600) {
                        unlink($lockFile);
                        if (file_exists($file)) {
                            unlink($file);
                        }
                        echo "unlocked ($age): $lockFile\n";
                    } else {
                        echo "locked ($age): $lockFile\n";
                    }
                    ob_flush();
                    continue;
                }

                touch($lockFile);

                if (file_exists($file)) {
                    echo "exists: $file\n";
                    ob_flush();
                    continue;
                }

                echo "downloading: $url\n";
                ob_flush();

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

                $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
                $size = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
                echo "\nexpected size: " . $contentLength;
                echo "\ndownloaded size: " . $size;
                echo "\n";
                if (
                    200 == curl_getinfo($ch, CURLINFO_HTTP_CODE)
                    && ($contentLength === curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD) || intval($contentLength) === -1 && $size > 0)
                ) {
                    fclose($fp);
                    unlink($downloadFile);
                    echo "success\n";
                } else {
                    echo "error\n";
                    unlink($file);
                }
                ob_flush();
            } catch (Throwable $exception) {
                error_log((string)$exception);
                unlink($file);
            }
            if (file_exists($lockFile)) {
                unlink($lockFile);
            }
        }
    }
}