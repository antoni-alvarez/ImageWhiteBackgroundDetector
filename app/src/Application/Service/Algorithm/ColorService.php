<?php

declare(strict_types=1);

namespace App\Application\Service\Algorithm;

use function abs;

class ColorService
{
    private const WHITE_VALUE = 255;
    private const WHITE_LIKE_MIN_VALUE = 230;
    private const ALPHA_VALUE = 127;
    private const MAX_DISTANCE = 8;

    /**
     * @param array<string, int> $pixelColor
     */
    public function isWhitePixel(array $pixelColor, bool $strictMode = false): bool
    {
        return $strictMode ? $this->isPureWhitePixel($pixelColor) : $this->isWhiteLikePixel($pixelColor);
    }

    /**
     * @param array<string, int> $pixelColor
     */
    public function isTransparentPixel(array $pixelColor): bool
    {
        return $pixelColor['alpha'] === self::ALPHA_VALUE;
    }

    /**
     * @param array<string, int> $rgb
     */
    private function isPureWhitePixel(array $rgb): bool
    {
        return $rgb['red'] === self::WHITE_VALUE && $rgb['green'] === self::WHITE_VALUE && $rgb['blue'] === self::WHITE_VALUE;
    }

    /**
     * @param array<string, int> $rgb
     */
    private function isWhiteLikePixel(array $rgb): bool
    {
        if ($this->isPureWhitePixel($rgb)) {
            return true;
        }

        if ($rgb['red'] < self::WHITE_LIKE_MIN_VALUE
            || $rgb['green'] < self::WHITE_LIKE_MIN_VALUE
            || $rgb['blue'] < self::WHITE_LIKE_MIN_VALUE) {
            return false;
        }

        return $this->getMeanColorDistance($rgb) < self::MAX_DISTANCE;
    }

    /**
     * @param array<string, int> $rgb
     */
    private function getMeanColorDistance(array $rgb): float
    {
        $distanceRB = abs($rgb['red'] - $rgb['blue']);
        $distanceGR = abs($rgb['green'] - $rgb['red']);
        $distanceBG = abs($rgb['blue'] - $rgb['green']);

        return ($distanceRB + $distanceGR + $distanceBG) / 3;
    }
}
