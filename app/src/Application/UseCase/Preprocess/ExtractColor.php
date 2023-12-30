<?php

declare(strict_types=1);

namespace App\Application\UseCase\Preprocess;

use GdImage;

use function imagecolorat;
use function imagesx;
use function imagesy;

class ExtractColor
{
    public function __construct() {}

    /**
     * @return array<int, bool|int|float>
     */
    public function execute(GdImage $image, bool $isValid): array
    {
        $colorData = [$isValid];

        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);

        for ($x = 0; $x < $imageWidth; ++$x) {
            for ($y = 0; $y < $imageHeight; ++$y) {
                $colorData[] = (imagecolorat($image, $x, $y) & 0xFF) / 255;
            }
        }

        return $colorData;
    }
}
