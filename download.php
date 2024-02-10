<?php

declare(strict_types=1);

http_response_code(200);
flush();

set_time_limit(0);
ini_set('max_execution_time', '0');

echo '<pre>';

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

foreach (glob(__DIR__ . '/channel/*.channel') as $channelFile) {
    $lockFile = $channelFile . '.lock';
    $file = dirname($channelFile) . DIRECTORY_SEPARATOR . basename($channelFile, '.channel');
    $channelId = basename($channelFile, '.channel');
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
            flush();
            continue;
        }

        touch($lockFile);

        if (file_exists($file)) {
            $fileTime = filemtime($file);
            $age = time() - $file;
            if ($age > 30) {
                unlink($file);
            } else {
                echo "exists: $file\n";
                flush();
                continue;
            }
        }

        $url = 'https://' . $_SERVER['HTTP_HOST'] . '/channel/' . $channelId;


        echo "download: $url\n";
        flush();

        $fp = fopen($file, 'w+');

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'],
            CURLOPT_FILE => $fp,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYSTATUS => false,
            CURLOPT_RESOLVE => [$_SERVER['HTTP_USER_AGENT'] . ':443:127.0.0.1'],
        ]);

        curl_close($ch);

        echo "\nstatus: " . curl_getinfo($ch, CURLINFO_HTTP_CODE);
        echo "\nexpected size: " . curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);;
        echo "\ndownloaded size: " . curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);

        if (
            200 == curl_getinfo($ch, CURLINFO_HTTP_CODE)
            && curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD) === curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD)
        ) {
            unlink($channelFile);
        } else {
            echo "\nerror: " . curl_error($ch);
            var_dump(curl_getinfo($ch));
            unlink($file);
        }
        echo "\n";

        unlink($lockFile);
        flush();
    } catch (Throwable $exception) {
        error_log((string)$exception);
        echo $exception;
        unlink($channelFile);
        unlink($file);
        unlink($lockFile);
    }
}

foreach (glob(__DIR__ . '/videos/*.url') as $urlFile) {
    ob_flush();
    $lockFile = $urlFile . '.lock';
    $file = dirname($urlFile) . DIRECTORY_SEPARATOR . basename($urlFile, '.url');
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
            flush();
            continue;
        }

        touch($lockFile);

        $url = file_get_contents($urlFile);

        if (file_exists($file)) {
            $fileTime = filemtime($file);
            $age = time() - $fileTime;
            if ($age > 172800) {
                unlink($file);
            } else {
                echo "exists: $file\n";
                flush();
                continue;
            }
        }

        echo "download: $urlFile\n";
        flush();

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
        echo "\n";
        if (
            200 == curl_getinfo($ch, CURLINFO_HTTP_CODE)
            && curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD) === curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD)
        ) {
            unlink($urlFile);
        } else {
            unlink($file);
        }
        unlink($lockFile);
        flush();
    } catch (Throwable $exception) {
        error_log((string)$exception);
        unlink($urlFile);
        unlink($file);
        unlink($lockFile);
    }
}
echo '</pre>';
exit;
