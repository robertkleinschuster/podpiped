<?php

declare(strict_types=1);

require_once "Log.class.php";

class ImageConverter
{
    private Log $log;

    public function __construct(private string $base = __DIR__, private string $folder = DIRECTORY_SEPARATOR . 'static')
    {
        if (!is_dir($this->base . $this->folder)) {
            mkdir($this->base . $this->folder);
        }
        $this->log = new Log();
    }

    public function schedule(string $file): string
    {
        $name = $this->folder . DIRECTORY_SEPARATOR . md5($file);
        $outfile = dirname($file) . DIRECTORY_SEPARATOR . basename($file) . '.jpg';

        if (!file_exists($this->base . $outfile)) {
            file_put_contents($this->base . DIRECTORY_SEPARATOR . $name . '.convert', json_encode([
                'file' => $file,
                'outfile' => $outfile,
                'width' => 700,
                'height' => 700
            ]));
        }

        return $outfile;
    }

    public function convert(): void
    {
        ini_set('memory_limit', '50M');

        $images = glob($this->base . $this->folder . DIRECTORY_SEPARATOR . '*.convert');
        foreach ($images as $imageFile) {
            if (!file_exists($imageFile)) {
                continue;
            }
            $image = json_decode(file_get_contents($imageFile), true);
            $file = __DIR__ . $image['file'];
            $outfile = __DIR__ . $image['outfile'];
            $width = $image['width'];
            $heigt = $image['height'];

            if (!file_exists($file)) {
                continue;
            }

            if (file_exists($outfile)) {
                unlink($imageFile);
                continue;
            }

            $this->log->append("converting: $file");

            try {
                $sourceImage = null;
                if (exif_imagetype($file) === IMAGETYPE_JPEG) {
                    $sourceImage = imagecreatefromjpeg($file);
                }

                if (exif_imagetype($file) === IMAGETYPE_WEBP) {
                    $sourceImage = imagecreatefromwebp($file);
                }

                if (exif_imagetype($file) === IMAGETYPE_PNG) {
                    $sourceImage = imagecreatefrompng($file);
                }

                if (!$sourceImage) {
                    continue;
                }

                $sourceImageX = imagesx($sourceImage);
                $sourceImageY = imagesy($sourceImage);

                $destImageX = $width;
                $destImageY = intval($destImageX * ($sourceImageY / $sourceImageX));

                $destImage = imagecreatetruecolor($width, $heigt);
                imagecopyresampled(
                    $destImage,
                    $sourceImage,
                    0,
                    intval(($heigt / 2) - $destImageY / 2),
                    0,
                    0,
                    $destImageX,
                    $destImageY,
                    $sourceImageX,
                    $sourceImageY
                );

                imagejpeg($destImage, $outfile);
                unlink($imageFile);
            } catch (Throwable $exception) {
                $this->log->append((string)$exception);
                unlink($imageFile);
            }

        }
    }
}