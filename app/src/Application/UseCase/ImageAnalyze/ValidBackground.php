<?php

declare(strict_types=1);

namespace App\Application\UseCase\ImageAnalyze;

use App\Application\Service\ColorService;
use Exception;
use GdImage;
use Random\Randomizer;
use Symfony\Component\Filesystem\Exception\IOException;

use function file_get_contents;
use function imagecolorat;
use function imagecolorsforindex;
use function imagecreatefromstring;
use function imagesx;
use function imagesy;
use function max;
use function sprintf;

class ValidBackground
{
    private const int NUM_POINTS = 2500;
    private const float MIN_BORDER_VALID_PERCENTAGE = 0.6;
    private const float MIN_SINGLE_BORDER_VALID_PERCENTAGE = 0.3;

    /**
     * Google recommendation: 0.1 to 0.25
     * "Frame your product in the image space so that it takes up no less than 75%, but not more than 90%, of the full image.".
     */
    private const float BORDER_SIZE = 0.01;

    private bool $strictMode = false;

    public function __construct(
        private readonly ColorService $colorService,
        private readonly Randomizer $randomizer,
    ) {}

    public function execute(string $imagePath): bool
    {
        $image = $this->loadImage($imagePath);

        return $this->hasValidBackground($image);
    }

    public function hasValidBackground(GdImage $image): bool
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $borderWidth = max(1, (int) ($width * self::BORDER_SIZE));
        $borderHeight = max(1, (int) ($height * self::BORDER_SIZE));

        $topWhitePoints = 0;
        $rightWhitePoints = 0;
        $bottomWhitePoints = 0;
        $leftWhitePoints = 0;

        $topTransparentPoints = 0;
        $rightTransparentPoints = 0;
        $bottomTransparentPoints = 0;
        $leftTransparentPoints = 0;

        for ($i = 0; $i < self::NUM_POINTS; $i++) {
            $pixelColor = $this->getTopBorderRandomPixelColor($width, $borderWidth, $borderHeight, $image);

            if ($this->colorService->isWhitePixel($pixelColor, $this->strictMode)) {
                $topWhitePoints++;
            }

            if ($this->colorService->isTransparentPixel($pixelColor)) {
                $topTransparentPoints++;
            }
        }

        $topWhitePercentage = $topWhitePoints / self::NUM_POINTS;
        $topTransparentPercentage = $topTransparentPoints / self::NUM_POINTS;

        if ($topWhitePercentage < self::MIN_SINGLE_BORDER_VALID_PERCENTAGE && $topTransparentPercentage < self::MIN_SINGLE_BORDER_VALID_PERCENTAGE) {
            return false;
        }

        for ($i = 0; $i < self::NUM_POINTS; $i++) {
            $pixelColor = $this->getRightBorderRandomPixelColor($width, $height, $borderWidth, $image);

            if ($this->colorService->isWhitePixel($pixelColor)) {
                $rightWhitePoints++;
            }

            if ($this->colorService->isTransparentPixel($pixelColor)) {
                $rightTransparentPoints++;
            }
        }

        $rightWhitePercentage = $rightWhitePoints / self::NUM_POINTS;
        $rightTransparentPercentage = $rightTransparentPoints / self::NUM_POINTS;

        if ($rightWhitePercentage < self::MIN_SINGLE_BORDER_VALID_PERCENTAGE && $rightTransparentPercentage < self::MIN_SINGLE_BORDER_VALID_PERCENTAGE) {
            return false;
        }

        for ($i = 0; $i < self::NUM_POINTS; $i++) {
            $pixelColor = $this->getBottomBorderRandomPixelColor($width, $height, $borderWidth, $borderHeight, $image);

            if ($this->colorService->isWhitePixel($pixelColor)) {
                $bottomWhitePoints++;
            }

            if ($this->colorService->isTransparentPixel($pixelColor)) {
                $bottomTransparentPoints++;
            }
        }

        $bottomWhitePercentage = $bottomWhitePoints / self::NUM_POINTS;
        $bottomTransparentPercentage = $bottomTransparentPoints / self::NUM_POINTS;

        if ($bottomWhitePercentage < self::MIN_SINGLE_BORDER_VALID_PERCENTAGE && $bottomTransparentPercentage < self::MIN_SINGLE_BORDER_VALID_PERCENTAGE) {
            return false;
        }

        for ($i = 0; $i < self::NUM_POINTS; $i++) {
            $pixelColor = $this->getLeftBorderRandomPixelColor($height, $borderWidth, $image);

            if ($this->colorService->isWhitePixel($pixelColor)) {
                $leftWhitePoints++;
            }

            if ($this->colorService->isTransparentPixel($pixelColor)) {
                $leftTransparentPoints++;
            }
        }

        $leftWhitePercentage = $leftWhitePoints / self::NUM_POINTS;
        $leftTransparentPercentage = $leftTransparentPoints / self::NUM_POINTS;

        if ($leftWhitePercentage < self::MIN_SINGLE_BORDER_VALID_PERCENTAGE && $leftTransparentPercentage < self::MIN_SINGLE_BORDER_VALID_PERCENTAGE) {
            return false;
        }

        $sumWhitePercentage = $topWhitePercentage + $rightWhitePercentage + $bottomWhitePercentage + $leftWhitePercentage;
        $sumTransparentPercentage = $topTransparentPercentage + $rightTransparentPercentage + $bottomTransparentPercentage + $leftTransparentPercentage;

        $meanWhitePercentage = $sumWhitePercentage / 4;
        $meanTransparentPercentage = $sumTransparentPercentage / 4;

        return $meanWhitePercentage > self::MIN_BORDER_VALID_PERCENTAGE || $meanTransparentPercentage > self::MIN_BORDER_VALID_PERCENTAGE;
    }

    public function setStrictMode(bool $strictMode): void
    {
        $this->strictMode = $strictMode;
    }

    /**
     * @return array<string, int>
     */
    private function getTopBorderRandomPixelColor(int $width, int $borderWidth, int $borderHeight, GdImage $image): array
    {
        $x = $this->randomizer->getInt($borderWidth, $width - $borderWidth);
        $y = $this->randomizer->getInt(0, $borderHeight - 1);

        $color = imagecolorat($image, $x, $y);

        if ($color === false) {
            throw new IOException(sprintf('Error reading color info at top border %s:%s', $x, $y));
        }

        return imagecolorsforindex($image, $color);
    }

    /**
     * @return array<string, int>
     */
    private function getRightBorderRandomPixelColor(int $width, int $height, int $borderWidth, GdImage $image): array
    {
        $x = $this->randomizer->getInt($width - $borderWidth, $width - 1);
        $y = $this->randomizer->getInt(0, $height - 1);

        $color = imagecolorat($image, $x, $y);

        if ($color === false) {
            throw new IOException(sprintf('Error reading color info at right border %s:%s', $x, $y));
        }

        return imagecolorsforindex($image, $color);
    }

    /**
     * @return array<string, int>
     */
    private function getBottomBorderRandomPixelColor(int $width, int $height, int $borderWidth, int $borderHeight, GdImage $image): array
    {
        $x = $this->randomizer->getInt($borderWidth, $width - $borderWidth);
        $y = $this->randomizer->getInt($height - $borderHeight, $height - 1);

        $color = imagecolorat($image, $x, $y);

        if ($color === false) {
            throw new IOException(sprintf('Error reading color info at bottom border %s:%s', $x, $y));
        }

        return imagecolorsforindex($image, $color);
    }

    /**
     * @return array<string, int>
     */
    private function getLeftBorderRandomPixelColor(int $height, int $borderWidth, GdImage $image): array
    {
        $x = $this->randomizer->getInt(0, $borderWidth - 1);
        $y = $this->randomizer->getInt(0, $height - 1);

        $color = imagecolorat($image, $x, $y);

        if ($color === false) {
            throw new IOException(sprintf('Error reading color info at left border %s:%s', $x, $y));
        }

        return imagecolorsforindex($image, $color);
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
