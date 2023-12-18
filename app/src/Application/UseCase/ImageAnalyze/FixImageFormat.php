<?php

declare(strict_types=1);

namespace App\Application\UseCase\ImageAnalyze;

use Exception;
use InvalidArgumentException;
use RuntimeException;

use Symfony\Component\Filesystem\Exception\IOException;
use function file_exists;
use function file_get_contents;
use function getimagesize;
use function imagecreatefromstring;
use function imagedestroy;
use function pathinfo;
use function rename;
use function sprintf;

use const DIRECTORY_SEPARATOR;
use const IMAGETYPE_JPEG;
use const IMAGETYPE_PNG;
use const IMAGETYPE_WEBP;
use const PATHINFO_DIRNAME;
use const PATHINFO_FILENAME;

class FixImageFormat
{
    public function execute(string $oldImagePath): bool
    {
        $file = file_get_contents($oldImagePath);

        if ($file === false) {
            throw new IOException(sprintf('Error opening file %s', $oldImagePath));
        }

        try {
            $image = imagecreatefromstring($file);
        } catch (Exception) {
            throw new Exception(sprintf('Critical error reading image %s', $oldImagePath));
        }

        if ($image === false) {
            throw new Exception(sprintf('Error opening image %s', $oldImagePath));
        }

        $newImageName = $this->getFixedImageName($oldImagePath);

        $newImagePath = pathinfo($oldImagePath, PATHINFO_DIRNAME) . $newImageName;

        if (false === rename($oldImagePath, $newImagePath)) {
            throw new RuntimeException(sprintf('Error changing file extension for image %s', $oldImagePath));
        }

        imagedestroy($image);

        return $oldImagePath !== $newImagePath;
    }

    private function getFixedImageName(string $imagePath): string
    {
        if (!file_exists($imagePath)) {
            throw new InvalidArgumentException(sprintf('The image does not exist at the specified path: %s', $imagePath));
        }

        $info = getimagesize($imagePath);

        if ($info === false) {
            throw new RuntimeException(sprintf('The image at path %s is not valid.', $imagePath));
        }

        $newFileName = pathinfo($imagePath, PATHINFO_FILENAME);

        $newFileName .= match ($info[2]) {
            IMAGETYPE_JPEG => '.jpg',
            IMAGETYPE_WEBP => '.webp',
            IMAGETYPE_PNG => '.png',
            default => throw new RuntimeException(sprintf('Unsupported image format at path %s', $imagePath)),
        };

        return DIRECTORY_SEPARATOR . $newFileName;
    }
}
