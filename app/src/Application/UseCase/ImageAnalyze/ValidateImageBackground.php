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
    private const float MIN_INNER_BORDER_VALID_PERCENTAGE = 0.6;
    private const float MIN_INNER_SINGLE_BORDER_VALID_PERCENTAGE = 0.3;
    private const float MIN_OUTER_BORDER_VALID_PERCENTAGE = 0.75;
    private const float MIN_OUTER_SINGLE_BORDER_VALID_PERCENTAGE = 0.4;

    /**
     * Google recommendation: 0.1 to 0.25
     * "Frame your product in the image space so that it takes up no less than 75%, but not more than 90%, of the full image.".
     */
    private const float INNER_BORDER_SIZE = 0.01;
    private const float OUTER_BORDER_SIZE = 0.001;

    public function __construct(
        private BorderAnalyzeService $borderAnalyzeService,
    ) {}

    public function execute(string $imagePath, bool $strictMode): bool
    {
        $image = $this->loadImage($imagePath);

        $this->borderAnalyzeService->setImage($image);

        if (false === $this->borderAnalyzeService->isValidBorder(
            self::OUTER_BORDER_SIZE,
            self::MIN_OUTER_BORDER_VALID_PERCENTAGE,
            self::MIN_OUTER_SINGLE_BORDER_VALID_PERCENTAGE,
            $strictMode,
        )) {
            return false;
        }

        return $this->borderAnalyzeService->isValidBorder(
            self::INNER_BORDER_SIZE,
            self::MIN_INNER_BORDER_VALID_PERCENTAGE,
            self::MIN_INNER_SINGLE_BORDER_VALID_PERCENTAGE,
            $strictMode,
        );
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
