<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Enum\BorderSide;
use GdImage;
use Random\Randomizer;
use Symfony\Component\Filesystem\Exception\IOException;

use function imagecolorat;
use function imagecolorsforindex;
use function imagesx;
use function imagesy;
use function max;
use function sprintf;

class BorderAnalyzeService
{
    private const int NUM_POINTS = 2500;

    private GdImage $image;
    private int $width;
    private int $height;
    private int $borderWidth;
    private int $borderHeight;

    public function __construct(
        private readonly ColorService $colorService,
        private readonly Randomizer $randomizer,
    ) {}

    public function setImage(GdImage $image): void
    {
        $this->image = $image;
    }

    public function isValidBorder(
        float $borderSize,
        float $minBorderPercentage,
        float $minSingleBorderPercentage,
        bool $strictMode = false,
    ): bool {
        $this->calculateBorders($borderSize);

        if (false === $topBorder = $this->analyzeBorder(BorderSide::TOP, $minSingleBorderPercentage, $strictMode)) {
            return false;
        }

        if (false === $rightBorder = $this->analyzeBorder(BorderSide::RIGHT, $minSingleBorderPercentage, $strictMode)) {
            return false;
        }

        if (false === $bottomBorder = $this->analyzeBorder(BorderSide::BOTTOM, $minSingleBorderPercentage, $strictMode)) {
            return false;
        }

        if (false === $leftBorder = $this->analyzeBorder(BorderSide::LEFT, $minSingleBorderPercentage, $strictMode)) {
            return false;
        }

        $sumWhitePercentage = $topBorder['white'] + $rightBorder['white'] + $bottomBorder['white'] + $leftBorder['white'];
        $sumAlphaPercentage = $topBorder['alpha'] + $rightBorder['alpha'] + $bottomBorder['alpha'] + $leftBorder['alpha'];

        $meanWhitePercentage = $sumWhitePercentage / 4;
        $meanAlphaPercentage = $sumAlphaPercentage / 4;

        return $meanWhitePercentage > $minBorderPercentage || $meanAlphaPercentage > $minBorderPercentage;
    }

    private function calculateBorders(float $borderPercentage): void
    {
        $this->width = imagesx($this->image);
        $this->height = imagesy($this->image);

        $this->borderWidth = max(1, (int) ($this->width * $borderPercentage));
        $this->borderHeight = max(1, (int) ($this->height * $borderPercentage));
    }

    /**
     * @return false|array{white: float, alpha: float}
     */
    private function analyzeBorder(BorderSide $border, float $minSingleBorderPercentage, bool $strictMode = false): array|false
    {
        $whitePoints = 0;
        $alphaPoints = 0;

        for ($i = 0; $i < self::NUM_POINTS; $i++) {
            $pixelColor = $this->getRandomPixelColor($border);

            if ($this->colorService->isWhitePixel($pixelColor, $strictMode)) {
                $whitePoints++;
            }

            if ($this->colorService->isTransparentPixel($pixelColor)) {
                $alphaPoints++;
            }
        }

        $whitePercentage = $whitePoints / self::NUM_POINTS;
        $alphaPercentage = $alphaPoints / self::NUM_POINTS;

        $border = ['white' => $whitePercentage, 'alpha' => $alphaPercentage];

        if (false === $this->isValidSingleBorder($border, $minSingleBorderPercentage)) {
            return false;
        }

        return $border;
    }

    /**
     * @param array{white: float, alpha: float} $border
     */
    private function isValidSingleBorder(array $border, float $minSingleBorderPercentage): bool
    {
        return $border['white'] >= $minSingleBorderPercentage || $border['alpha'] >= $minSingleBorderPercentage;
    }

    /**
     * @return array<string, int>
     */
    private function getRandomPixelColor(BorderSide $border): array
    {
        switch ($border) {
            case BorderSide::TOP:
                $x = $this->randomizer->getInt($this->borderWidth, $this->width - $this->borderWidth);
                $y = $this->randomizer->getInt(0, $this->borderHeight - 1);
                break;
            case BorderSide::RIGHT:
                $x = $this->randomizer->getInt($this->width - $this->borderWidth, $this->width - 1);
                $y = $this->randomizer->getInt(0, $this->height - 1);
                break;
            case BorderSide::BOTTOM:
                $x = $this->randomizer->getInt($this->borderWidth, $this->width - $this->borderWidth);
                $y = $this->randomizer->getInt($this->height - $this->borderHeight, $this->height - 1);
                break;
            case BorderSide::LEFT:
                $x = $this->randomizer->getInt(0, $this->borderWidth - 1);
                $y = $this->randomizer->getInt(0, $this->height - 1);
                break;
        }

        $color = imagecolorat($this->image, $x, $y);

        if ($color === false) {
            throw new IOException(sprintf('Error reading color info at %s border %s:%s', $border->value, $x, $y));
        }

        return imagecolorsforindex($this->image, $color);
    }
}
