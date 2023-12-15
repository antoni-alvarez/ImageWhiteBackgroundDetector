<?php

declare(strict_types=1);

namespace App\Application\UseCase\ImageAnalyze;

use Exception;
use GdImage;
use Random\Randomizer;

use function file_get_contents;
use function imagecolorat;
use function imagecolorsforindex;
use function imagecreatefromstring;
use function imagedestroy;
use function imagesx;
use function imagesy;
use function max;
use function mt_rand;
use function sprintf;

class AnalyzeImageBackground
{
    private const int NUM_POINTS = 5000;
    private const int BORDER_TOP = 1;
    private const int BORDER_BOTTOM = 2;
    private const int BORDER_LEFT = 3;
    private const int BORDER_RIGHT = 4;
    private const int WHITE_THRESHOLD = 220;
    private const int ALPHA_VALUE = 127;
    private const float MIN_BACKGROUND_THRESHOLD = 0.5;
    private const float BORDER_PERCENTAGE = 0.1;

    public function __construct(
        private readonly Randomizer $randomizer,
    ) {}

    public function execute(string $imagePath): bool
    {
        $file = file_get_contents($imagePath);

        try {
            $image = imagecreatefromstring($file);
        } catch (Exception) {
            throw new Exception(sprintf('Critical error reading image %s', $oldImagePath));
        }

        if ($image === false) {
            throw new Exception(sprintf('Error opening image %s', $imagePath));
        }

        $hasWhiteBackground = $this->detectWhiteBackground($image);

        imagedestroy($image);

        return $hasWhiteBackground;
    }

    public function detectWhiteBackground(GdImage $image): bool
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $borderWidth = max(1, (int) ($width * self::BORDER_PERCENTAGE));
        $borderHeight = max(1, (int) ($height * self::BORDER_PERCENTAGE));

        $whitePoints = 0;
        $transparentPoints = 0;

        for ($i = 0; $i < self::NUM_POINTS; $i++) {
            $border = mt_rand(self::BORDER_TOP, self::BORDER_RIGHT);

            switch ($border) {
                case self::BORDER_TOP:
                    $x = mt_rand(0, $width - 1);
                    $y = mt_rand(0, (int) $borderHeight - 1);
                    break;
                case self::BORDER_BOTTOM:
                    $x = mt_rand(0, $width - 1);
                    $y = mt_rand((int) ($height - $borderHeight), $height - 1);
                    break;
                case self::BORDER_LEFT:
                    $x = mt_rand(0, (int) $borderWidth - 1);
                    $y = mt_rand(0, $height - 1);
                    break;
                case self::BORDER_RIGHT:
                    $x = mt_rand((int) ($width - $borderWidth), $width - 1);
                    $y = mt_rand(0, $height - 1);
                    break;
            }

            $color = imagecolorat($image, $x, $y);
            $rgb = imagecolorsforindex($image, $color);

            if ($rgb['red'] > self::WHITE_THRESHOLD && $rgb['green'] > self::WHITE_THRESHOLD && $rgb['blue'] > self::WHITE_THRESHOLD) {
                $whitePoints++;
            }

            if ($rgb['alpha'] === self::ALPHA_VALUE) {
                $transparentPoints++;
            }
        }

        $whitePointsRatio = $whitePoints / self::NUM_POINTS;
        $transparentRatio = $transparentPoints / self::NUM_POINTS;

        return $whitePointsRatio >= self::MIN_BACKGROUND_THRESHOLD || $transparentRatio >= self::MIN_BACKGROUND_THRESHOLD;
    }
}
