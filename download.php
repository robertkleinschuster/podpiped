<?php

declare(strict_types=1);

http_response_code(200);
flush();

set_time_limit(0);
ini_set('max_execution_time', '0');

foreach (glob(__DIR__ . '/videos/*.mp4') as $videoFile) {
    try {
        $fileTime = filemtime($videoFile);
        $age = time() - $fileTime;
        if ($age > 172800) {
            unlink($videoFile);
        }
    } catch (Throwable $exception) {
        error_log((string)$exception);
    }
}
echo '<pre>';

foreach (glob(__DIR__ . '/videos/*.url') as $urlFile) {
    $lockFile = $urlFile . '.lock';
    $file = dirname($urlFile) . DIRECTORY_SEPARATOR . basename($urlFile, '.url');
    try {
        if (file_exists($lockFile)) {
            $fileTime = filemtime($lockFile);
            $age = time() - $fileTime;
            if ($age > 3600) {
                unlink($lockFile);
                unlink($file);
                echo "unlocked ($age): $lockFile\n";
            } else {
                echo "locked ($age): $lockFile\n";
            }
            continue;
        }

        touch($lockFile);

        $url = file_get_contents($urlFile);

        if (file_exists($file)) {
            echo "exists: $file\n";
            continue;
        }

        echo "download: $urlFile\n";

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

        echo "\nexpected size: ";
        echo curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        echo "\ndownloaded size: ";
        echo curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        if (
            200 == curl_getinfo($ch, CURLINFO_HTTP_CODE)
            && curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD) === curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD)
        ) {
            unlink($urlFile);
            unlink($lockFile);
        } else {
            unlink($file);
        }
    } catch (Throwable $exception) {
        error_log((string)$exception);
        unlink($urlFile);
        unlink($file);
        unlink($lockFile);
    }
}
echo '</pre>';


