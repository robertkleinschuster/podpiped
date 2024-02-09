<?php

declare(strict_types=1);

set_time_limit(0);

foreach (glob(__DIR__ . '/videos/*.url') as $urlFile) {
    try {
        $url = file_get_contents($urlFile);

        $file = dirname($urlFile) . DIRECTORY_SEPARATOR . basename($urlFile, '.url');

        if (file_exists($file)) {
            continue;
        }

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

        echo '<pre>';
        echo $urlFile;
        echo "\n";
        echo curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        echo "\n";
        echo curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        echo '</pre>';
        if (
            200 == curl_getinfo($ch, CURLINFO_HTTP_CODE)
            && curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD) === curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD)
        ) {
            unlink($urlFile);
        } else {
            unlink($file);
        }
    } catch (Throwable $exception) {
        error_log((string)$exception);
        unlink($urlFile);
        unlink($file);
    }
}

