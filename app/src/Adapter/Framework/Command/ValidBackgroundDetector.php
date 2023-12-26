<?php

declare(strict_types=1);

namespace App\Adapter\Framework\Command;

use App\Application\UseCase\ImageAnalyze\ValidBackground;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;

use function array_diff;
use function array_map;
use function count;
use function dirname;
use function microtime;
use function number_format;
use function pathinfo;
use function sprintf;

use const PATHINFO_BASENAME;

#[AsCommand(
    name: 'image:analyze',
    description: 'Detect valid background on images',
)]
class ValidBackgroundDetector extends Command
{
    private const string VALID_IMAGES_PATH = '/public/images/valid';
    private const string INVALID_IMAGES_PATH = '/public/images/invalid';

    public function __construct(
        private readonly ValidBackground $validBackground,
        private readonly KernelInterface $kernel,
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addOption('valid', mode: InputOption::VALUE_NONE);
        $this->addOption('invalid', mode: InputOption::VALUE_NONE);
        $this->addOption('strict', mode: InputOption::VALUE_OPTIONAL, default: false);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Starting valid image background detection..');

        $strictMode = $input->getOption('strict') !== false;
        $valid = $input->getOption('valid');
        $invalid = $input->getOption('invalid');
        $both = !$valid && !$invalid;

        $this->validBackground->setStrictMode($strictMode);

        if ($valid || $both) {
            $this->analyzeValidImages($output, $io);
        }

        if ($invalid || $both) {
            $this->analyzeInvalidImages($output, $io);
        }

        return Command::SUCCESS;
    }

    private function analyzeValidImages(OutputInterface $output, SymfonyStyle $io): void
    {
        $validImages = $this->getFilesInDirectory(self::VALID_IMAGES_PATH);

        $progressBar = $this->getProgressBar($output, $validImages);

        $falsePositives = [];
        $startTime = microtime(true);

        foreach ($validImages as $image) {
            $progressBar->setMessage(sprintf('Processing valid image "%s"', $image), 'filename');
            $progressBar->display();
            $progressBar->advance();

            $hasWhiteBackground = $this->validBackground->execute($image);

            if (false === $hasWhiteBackground) {
                $falsePositives[] = $image;
            }
        }

        $progressBar->finish();

        $io->newLine();

        $elapsedTime = microtime(true) - $startTime;

        $this->copyFailedImages($validImages, $falsePositives);

        if ($output->isVerbose()) {
            $io->error(array_map(fn (string $falsePositive) => sprintf('Image %s detected as false positive', $falsePositive), $falsePositives));
        }

        if (count($falsePositives) === 0) {
            $io->success(sprintf(
                'Analyze completed in %s seconds. All images with white background passed the validation',
                number_format($elapsedTime, 3),
            ));
        } else {
            $io->error(sprintf(
                'Analyze completed in %s seconds. %s detected as false positive',
                number_format($elapsedTime, 3),
                count($falsePositives),
            ));
        }
    }

    private function analyzeInvalidImages(OutputInterface $output, SymfonyStyle $io): void
    {
        $invalidImages = $this->getFilesInDirectory(self::INVALID_IMAGES_PATH);

        $progressBar = $this->getProgressBar($output, $invalidImages);

        $falseNegatives = [];
        $startTime = microtime(true);

        foreach ($invalidImages as $image) {
            $progressBar->setMessage(sprintf('Processing invalid image "%s"', $image), 'filename');
            $progressBar->display();
            $progressBar->advance();

            if (true === $this->validBackground->execute($image)) {
                $falseNegatives[] = $image;
            }
        }

        $progressBar->finish();

        $io->newLine();

        $elapsedTime = microtime(true) - $startTime;

        $this->copyFailedImages($invalidImages, $falseNegatives);

        if ($output->isVerbose()) {
            $io->warning(array_map(fn (string $falseNegative) => sprintf('Image %s detected as false positive', $falseNegative), $falseNegatives));
        }

        $io->error(sprintf(
            'Analyze completed in %s seconds. %s detected as false negative. Rejection rate: %s',
            number_format($elapsedTime, 3),
            count($falseNegatives),
            (count($invalidImages) - count($falseNegatives)) / count($invalidImages) * 100,
        ));
    }

    /**
     * @param array<int, string> $allImages
     * @param array<int, string> $failedImages
     */
    private function copyFailedImages(array $allImages, array $failedImages): void
    {
        foreach ($failedImages as $image) {
            $failedDirectory = sprintf('%s/failed', dirname($image));
            $imageName = pathinfo($image, PATHINFO_BASENAME);
            $destination = sprintf('%s/%s', $failedDirectory, $imageName);

            $this->filesystem->mkdir($failedDirectory);
            $this->filesystem->copy($image, $destination);
        }

        $successImages = array_diff($allImages, $failedImages);

        foreach ($successImages as $image) {
            $successDirectory = sprintf('%s/success', dirname($image));
            $imageName = pathinfo($image, PATHINFO_BASENAME);
            $destination = sprintf('%s/%s', $successDirectory, $imageName);

            $this->filesystem->mkdir($successDirectory);
            $this->filesystem->copy($image, $destination);
        }
    }

    /**
     * @return array<int, string>
     */
    private function getFilesInDirectory(string $directory): array
    {
        $fullPath = sprintf('%s%s', $this->kernel->getProjectDir(), $directory);

        $failedPath = sprintf('%s%s', $fullPath, '/failed');
        $successPath = sprintf('%s%s', $fullPath, '/success');

        $this->filesystem->remove($failedPath);
        $this->filesystem->remove($successPath);

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
}
