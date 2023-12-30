<?php

declare(strict_types=1);

namespace App\Application\UseCase\Preprocess;

use Intervention\Image\ImageManager;
use InvalidArgumentException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

use function dirname;
use function file_exists;
use function getimagesize;
use function image_type_to_extension;
use function pathinfo;
use function sprintf;

use const IMAGETYPE_JPEG;
use const PATHINFO_FILENAME;

class ImagePreprocess
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly ImageManager $imageManager,
    ) {}

    public function execute(string $imagePath): void
    {
        $processedImagePath = $this->getJpegImagePath($imagePath);

        $image = $this->imageManager->read($imagePath);
        $image->greyscale();
        $image->scale(width: 256);

        if ($this->isAlphaImage($imagePath)) {
            $image->reduceColors(256, '#ffffff');
        }

        $image->contrast(10)->brightness(-10);
        $image->toJpeg(100);
        $image->save($processedImagePath);
    }

    private function getJpegImagePath(string $imagePath): string
    {
        if (!file_exists($imagePath)) {
            throw new InvalidArgumentException(sprintf('The image does not exist at the specified path: %s', $imagePath));
        }

        $processedDirectory = sprintf('%s/processed', dirname($imagePath));

        $this->filesystem->mkdir($processedDirectory);

        $info = getimagesize($imagePath);

        if ($info === false) {
            throw new IOException(sprintf('The image at path %s is not valid.', $imagePath));
        }

        return sprintf(
            '%s/%s%s',
            $processedDirectory,
            pathinfo($imagePath, PATHINFO_FILENAME),
            image_type_to_extension(IMAGETYPE_JPEG),
        );
    }

    private function isAlphaImage(string $imagePath): bool
    {
        if (!file_exists($imagePath)) {
            throw new InvalidArgumentException(sprintf('The image does not exist at the specified path: %s', $imagePath));
        }

        $imageInfo = getimagesize($imagePath);

        if ($imageInfo === false) {
            throw new IOException(sprintf('The image at path %s is not valid.', $imagePath));
        }

        return $imageInfo[2] === IMAGETYPE_PNG || $imageInfo[2] === IMAGETYPE_WEBP;
    }
}
