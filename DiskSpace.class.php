<?php

class DiskSpace
{
    private function getFolderSize($dir) {
        $size = 0;

        // Check if the directory exists
        if (is_dir($dir)) {
            // Open the directory
            $directory = opendir($dir);

            // Iterate through the directory contents
            while (($file = readdir($directory)) !== false) {
                // Skip the current directory (.) and parent directory (..)
                if ($file !== '.' && $file !== '..') {
                    $path = $dir . '/' . $file;

                    // Recursively calculate the size of subdirectories
                    if (is_dir($path)) {
                        $size += $this->getFolderSize($path);
                    } else {
                        // Add the file size to the total size
                        $size += filesize($path);
                    }
                }
            }

            // Close the directory
            closedir($directory);
        }

        return $size;
    }

    public function getSize($dir): float
    {
        return $this->getFolderSize($dir) / 1024 / 1024 / 1024;
    }
}