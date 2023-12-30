<?php

declare(strict_types=1);

namespace App\Application\UseCase\Preprocess;

use GdImage;

use function imagecolorat;
use function imagesx;
use function imagesy;
use function round;

class ExtractColor
{
    public function __construct() {}

    /**
     * @return array<int, float>
     */
    public function execute(GdImage $image): array
    {
        $colorData = [];

        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);

        for ($x = 0; $x < $imageWidth; ++$x) {
            for ($y = 0; $y < $imageHeight; ++$y) {
                $colorValue = imagecolorat($image, $x, $y) & 0xFF;
                $colorData[] = round($colorValue / 255, 3);
            }
        }

        return $colorData;
    }
}
