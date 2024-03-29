<?php

declare(strict_types=1);

namespace App\Adapter\Framework\Command\ML;

use App\Application\UseCase\MLPreprocess\ExtractColor;
use App\Application\UseCase\MLPreprocess\ImagePreprocess;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;

use function array_slice;
use function count;
use function fopen;
use function fputcsv;
use function microtime;
use function number_format;
use function sprintf;

#[AsCommand(
    name: 'image:preprocess',
    description: 'Preprocess images for machine learning background detector',
)]
class ImageMLPreprocess extends Command
{
    public const LIMIT = 'limit';
    public const VALID_IMAGES = 'valid';
    public const INVALID_IMAGES = 'invalid';
    private const IMAGES_PATH = '/public/images/%s';
    private const DATA_PATH = '/public/data.csv';

    public function __construct(
        private readonly ImagePreprocess $imagePreprocess,
        private readonly ExtractColor $extractColor,
        private readonly KernelInterface $kernel,
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    public function removePreviousProcessedImages(string $imagesPath): void
    {
        $processedPath = sprintf(
            '%s%s%s',
            $this->kernel->getProjectDir(),
            $imagesPath,
            '/processed',
        );

        $this->filesystem->remove($processedPath);
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addOption(self::LIMIT, mode: InputOption::VALUE_OPTIONAL);
        $this->addOption(self::VALID_IMAGES, mode: InputOption::VALUE_NONE);
        $this->addOption(self::INVALID_IMAGES, mode: InputOption::VALUE_NONE);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Starting image preprocessing for ML background detector..');

        /** @var string $limit */
        $limit = $input->getOption(self::LIMIT);
        $valid = $input->getOption(self::VALID_IMAGES);
        $invalid = $input->getOption(self::INVALID_IMAGES);
        $both = !$valid && !$invalid;

        $this->filesystem->remove($this->getDataFile());

        if ($valid || $both) {
            $this->preprocessImages($output, $io, self::VALID_IMAGES, (int) $limit);
        }

        if ($invalid || $both) {
            $this->preprocessImages($output, $io, self::INVALID_IMAGES, (int) $limit);
        }

        return Command::SUCCESS;
    }

    private function preprocessImages(OutputInterface $output, SymfonyStyle $io, string $imagesType, int $limit): void
    {
        $imagesPath = sprintf(self::IMAGES_PATH, $imagesType);

        $this->removePreviousProcessedImages($imagesPath);

        $images = $this->getFilesInDirectory($imagesPath);

        if ($limit > 0) {
            $images = array_slice($images, 0, 50);
        }

        $progressBar = $this->getProgressBar($output, $images);

        $startTime = microtime(true);

        $csvFile = fopen($this->getDataFile(), 'a');

        if (false === $csvFile) {
            throw new IOException(sprintf('Error opening data file %s', $this->getDataFile()));
        }

        foreach ($images as $image) {
            $progressBar->setMessage(sprintf('Processing %s image "%s"', $imagesType, $image), 'filename');
            $progressBar->display();
            $progressBar->advance();

            $processedImage = $this->imagePreprocess->execute($image);
            $colorData = $this->extractColor->execute($processedImage);
            $colorData[] = $imagesType;

            fputcsv($csvFile, $colorData);
        }

        $progressBar->finish();

        $io->newLine();

        $elapsedTime = microtime(true) - $startTime;

        $io->success(sprintf(
            'Preprocessed %s %s images in %s seconds.',
            count($images),
            $imagesType,
            number_format($elapsedTime, 3),
        ));
    }

    /**
     * @return array<int, string>
     */
    private function getFilesInDirectory(string $directory): array
    {
        $fullPath = sprintf('%s%s', $this->kernel->getProjectDir(), $directory);

        $finder = new Finder();
        $finder->files()->in($fullPath);

        $files = [];

        foreach ($finder as $file) {
            $files[] = $file->getRealPath();
        }

        return $files;
    }

    /**
     * @param array<int, string> $validImages
     */
    private function getProgressBar(OutputInterface $output, array $validImages): ProgressBar
    {
        $progressBar = new ProgressBar($output, count($validImages));
        $progressBar->setBarCharacter('<fg=green;options=bold>■</>');
        $progressBar->setProgressCharacter('<fg=green;options=bold>➤</>');
        $progressBar->setEmptyBarCharacter('<fg=red>-</>');
        $progressBar->setFormat(
            "<fg=white;options=bold>%filename%</>\n%current%/%max% [<fg=blue;options=bold>%bar%</>] %percent:3s%%\n⏳%remaining:8s% remaining %memory:21s% \n",
        );

        return $progressBar;
    }

    private function getDataFile(): string
    {
        return sprintf(
            '%s%s',
            $this->kernel->getProjectDir(),
            self::DATA_PATH,
        );
    }
}
