<?php

namespace App\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;
use RuntimeException;

final class ImageUploadService
{
    public const MAX_IMAGE_BYTES = 204800;
    private const MAX_INPUT_BYTES = 10485760;
    private const MAX_PIXELS = 24000000;

    /**
     * Stores an uploaded image as a JPEG no larger than 200 KB.
     *
     * @return array{relative_path: string, absolute_path: string, original_name: string, mime_type: string, file_size: int}
     */
    public function store(UploadedFile $file, string $subdirectory): array
    {
        if (! extension_loaded('gd') || ! function_exists('imagejpeg')) {
            throw new RuntimeException('Ekstensi GD PHP diperlukan untuk kompresi gambar.');
        }
        if (! $file->isValid() || $file->hasMoved() || $file->getSize() <= 0) {
            throw new RuntimeException('File gambar tidak valid.');
        }
        if ($file->getSize() > self::MAX_INPUT_BYTES) {
            throw new RuntimeException('Ukuran gambar sebelum kompresi maksimal 10 MB.');
        }

        $imageInfo = @getimagesize($file->getTempName());
        $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG];
        if (defined('IMAGETYPE_WEBP')) {
            $allowedTypes[] = IMAGETYPE_WEBP;
        }
        if ($imageInfo === false || ! in_array((int) $imageInfo[2], $allowedTypes, true)) {
            throw new RuntimeException('Format gambar harus JPG, PNG, atau WEBP.');
        }

        $width = (int) $imageInfo[0];
        $height = (int) $imageInfo[1];
        if ($width < 1 || $height < 1 || $width * $height > self::MAX_PIXELS) {
            throw new RuntimeException('Dimensi gambar terlalu besar untuk diproses.');
        }

        $binary = file_get_contents($file->getTempName());
        $source = $binary === false ? false : @imagecreatefromstring($binary);
        if ($source === false) {
            throw new RuntimeException('Gambar tidak dapat dibaca untuk dikompres.');
        }

        $safeSubdirectory = trim(str_replace(['..', '\\'], '', $subdirectory), '/');
        $directory = rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $safeSubdirectory);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            imagedestroy($source);
            throw new RuntimeException('Folder penyimpanan gambar tidak dapat dibuat.');
        }

        $storedName = bin2hex(random_bytes(20)) . '.jpg';
        $destination = $directory . DIRECTORY_SEPARATOR . $storedName;
        $temporary = $directory . DIRECTORY_SEPARATOR . '.' . $storedName . '.tmp';

        try {
            foreach ([1600, 1280, 1024, 800, 640, 480] as $maxEdge) {
                $scale = min(1, $maxEdge / max($width, $height));
                $targetWidth = max(1, (int) round($width * $scale));
                $targetHeight = max(1, (int) round($height * $scale));
                $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
                try {
                    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
                    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

                    foreach ([82, 72, 62, 52, 42, 32] as $quality) {
                        imagejpeg($canvas, $temporary, $quality);
                        clearstatcache(true, $temporary);
                        if (is_file($temporary) && filesize($temporary) <= self::MAX_IMAGE_BYTES) {
                            rename($temporary, $destination);
                            return [
                                'relative_path' => $safeSubdirectory . '/' . $storedName,
                                'absolute_path' => $destination,
                                'original_name' => substr(trim((string) $file->getClientName()), 0, 255) ?: 'gambar.jpg',
                                'mime_type' => 'image/jpeg',
                                'file_size' => (int) filesize($destination),
                            ];
                        }
                    }
                } finally {
                    imagedestroy($canvas);
                }
            }
        } finally {
            imagedestroy($source);
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }

        throw new RuntimeException('Gambar tidak dapat dikompres hingga maksimal 200 KB. Gunakan foto dengan resolusi lebih kecil.');
    }
}
