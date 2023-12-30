<?php

declare(strict_types=1);

namespace App\Application\UseCase\Util;

use InvalidArgumentException;
use LogicException;
use Symfony\Component\Filesystem\Exception\IOException;

use function dirname;
use function file_exists;
use function getimagesize;
use function pathinfo;
use function rename;
use function sprintf;

use const DIRECTORY_SEPARATOR;
use const IMAGETYPE_JPEG;
use const IMAGETYPE_PNG;
use const IMAGETYPE_WEBP;
use const PATHINFO_FILENAME;

class FixImageExtension
{
    public function execute(string $oldImagePath): bool
    {
        $newImageName = $this->getFixedImageName($oldImagePath);

        $newImagePath = dirname($oldImagePath) . $newImageName;

        if (false === rename($oldImagePath, $newImagePath)) {
            throw new IOException(sprintf('Error changing file extension for image %s', $oldImagePath));
        }

        return $oldImagePath !== $newImagePath;
    }

    private function getFixedImageName(string $imagePath): string
    {
        if (!file_exists($imagePath)) {
            throw new InvalidArgumentException(sprintf('The image does not exist at the specified path: %s', $imagePath));
        }

        $info = getimagesize($imagePath);

        if ($info === false) {
            throw new IOException(sprintf('The image at path %s is not valid.', $imagePath));
        }

        $newFileName = pathinfo($imagePath, PATHINFO_FILENAME);

        $newFileName .= match ($info[2]) {
            IMAGETYPE_JPEG => '.jpg',
            IMAGETYPE_WEBP => '.webp',
            IMAGETYPE_PNG => '.png',
            default => throw new LogicException(sprintf('Unsupported image format at path %s', $imagePath)),
        };

        return DIRECTORY_SEPARATOR . $newFileName;
    }
}
