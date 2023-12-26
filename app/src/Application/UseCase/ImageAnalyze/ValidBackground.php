<?php

declare(strict_types=1);

namespace App\Application\UseCase\ImageAnalyze;

use App\Application\Service\BorderAnalyzeService;
use App\Domain\Enum\BorderSide;
use Exception;
use GdImage;
use Symfony\Component\Filesystem\Exception\IOException;

use function file_get_contents;
use function imagecreatefromstring;
use function sprintf;

class ValidBackground
{
    public function __construct(
        private readonly BorderAnalyzeService $borderAnalyzeService,
    ) {}

    public function execute(string $imagePath, bool $strictMode): bool
    {
        $image = $this->loadImage($imagePath);

        $this->borderAnalyzeService->setImage($image);

        return $this->hasValidBackground($strictMode);
    }

    public function hasValidBackground(bool $strictMode): bool
    {
        if (false === $topBorder = $this->borderAnalyzeService->analyzeBorder(BorderSide::TOP, $strictMode)) {
            return false;
        }

        if (false === $rightBorder = $this->borderAnalyzeService->analyzeBorder(BorderSide::RIGHT, $strictMode)) {
            return false;
        }

        if (false === $bottomBorder = $this->borderAnalyzeService->analyzeBorder(BorderSide::BOTTOM, $strictMode)) {
            return false;
        }

        if (false === $leftBorder = $this->borderAnalyzeService->analyzeBorder(BorderSide::LEFT, $strictMode)) {
            return false;
        }

        return $this->borderAnalyzeService->isValidBorder($topBorder, $rightBorder, $bottomBorder, $leftBorder);
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
