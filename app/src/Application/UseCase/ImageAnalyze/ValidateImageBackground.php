<?php

declare(strict_types=1);

namespace App\Application\UseCase\ImageAnalyze;

use App\Application\Service\BorderAnalyzeService;
use Exception;
use GdImage;
use Symfony\Component\Filesystem\Exception\IOException;

use function file_get_contents;
use function imagecreatefromstring;
use function sprintf;

readonly class ValidateImageBackground
{
    public function __construct(
        private BorderAnalyzeService $borderAnalyzeService,
    ) {}

    public function execute(string $imagePath, bool $strictMode): bool
    {
        $image = $this->loadImage($imagePath);

        $this->borderAnalyzeService->setImage($image);

        return $this->borderAnalyzeService->isValidBorder($strictMode);
    }

    private function loadImage(string $imagePath): GdImage
    {
        $file = file_get_contents($imagePath);

        if ($file === false) {
            throw new IOException(sprintf('Error opening file %s', $imagePath));
        }

        try {
            $image = imagecreatefromstring($file);
        } catch (Exception) {
            throw new IOException(sprintf('Critical error reading image %s', $imagePath));
        }

        if ($image === false) {
            throw new IOException(sprintf('Error opening image %s', $imagePath));
        }

        return $image;
    }
}
