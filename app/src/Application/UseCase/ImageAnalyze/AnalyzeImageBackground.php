<?php

declare(strict_types=1);

namespace App\Application\UseCase\ImageAnalyze;

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

class AnalyzeImageBackground
{
    private const int BORDER_TOP = 1;
    private const int BORDER_BOTTOM = 2;
    private const int BORDER_LEFT = 3;
    private const int BORDER_RIGHT = 4;

    private const int WHITE_VALUE = 255;
    private const int WHITE_LIKE_MIN_VALUE = 220;
    private const int ALPHA_VALUE = 127;

    private const int NUM_POINTS = 8000;
    private const float MIN_VALID_PERCENTAGE = 0.9;

    /**
     * Google recommendation:
     * "Frame your product in the image space so that it takes up no less than 75%, but not more than 90%, of the full image."
     */
    private const float BORDER_SIZE = 0.1;

    private bool $strictMode = false;

    public function __construct(
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

        $whitePoints = 0;
        $transparentPoints = 0;

        for ($i = 0; $i < self::NUM_POINTS; $i++) {
            $pixelColor = $this->getRandomBorderPixelColor($width, $borderHeight, $height, $borderWidth, $image);

            if ($this->isWhitePixel($pixelColor)) {
                $whitePoints++;
            }

            if ($this->isTransparentPixel($pixelColor)) {
                $transparentPoints++;
            }
        }

        $hasWhiteBackground = $whitePoints / self::NUM_POINTS >= self::MIN_VALID_PERCENTAGE;
        $hasTransparentBackground = $transparentPoints / self::NUM_POINTS >= self::MIN_VALID_PERCENTAGE;

        return $hasWhiteBackground  || $hasTransparentBackground;
    }

    public function setStrictMode(bool $strictMode): void
    {
        $this->strictMode = $strictMode;
    }

    /**
     * @return array<string, int>
     */
    private function getRandomBorderPixelColor(int $width, int $borderHeight, int $height, int $borderWidth, GdImage $image): array
    {
        $border = $this->randomizer->getInt(self::BORDER_TOP, self::BORDER_RIGHT);

        switch ($border) {
            case self::BORDER_TOP:
                $x = $this->randomizer->getInt(0, $width - 1);
                $y = $this->randomizer->getInt(0, $borderHeight - 1);
                break;
            case self::BORDER_BOTTOM:
                $x = $this->randomizer->getInt(0, $width - 1);
                $y = $this->randomizer->getInt($height - $borderHeight, $height - 1);
                break;
            case self::BORDER_LEFT:
                $x = $this->randomizer->getInt(0, $borderWidth - 1);
                $y = $this->randomizer->getInt(0, $height - 1);
                break;
            case self::BORDER_RIGHT:
            default:
                $x = $this->randomizer->getInt($width - $borderWidth, $width - 1);
                $y = $this->randomizer->getInt(0, $height - 1);
        }

        $color = imagecolorat($image, $x, $y);

        if ($color === false) {
            throw new IOException(sprintf('Error reading color info at %s:%s', $x, $y));
        }

        return imagecolorsforindex($image, $color);
    }

    /**
     * @param array<string, int> $pixelColor
     */
    private function isWhitePixel(array $pixelColor): bool
    {
        return $this->strictMode ? $this->isPureWhitePixel($pixelColor) : $this->isWhiteLikePixel($pixelColor);
    }

    /**
     * @param array<string, int> $pixelColor
     */
    private function isTransparentPixel(array $pixelColor): bool
    {
        return $pixelColor['alpha'] === self::ALPHA_VALUE;
    }

    /**
     * @param array<string, int> $pixelColor
     */
    private function isPureWhitePixel(array $pixelColor): bool
    {
        return $pixelColor['red'] === self::WHITE_VALUE && $pixelColor['green'] === self::WHITE_VALUE && $pixelColor['blue'] === self::WHITE_VALUE;
    }

    /**
     * @param array<string, int> $pixelColor
     */
    private function isWhiteLikePixel(array $pixelColor): bool
    {
        return $pixelColor['red'] > self::WHITE_LIKE_MIN_VALUE && $pixelColor['green'] > self::WHITE_LIKE_MIN_VALUE && $pixelColor['blue'] > self::WHITE_LIKE_MIN_VALUE;
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
