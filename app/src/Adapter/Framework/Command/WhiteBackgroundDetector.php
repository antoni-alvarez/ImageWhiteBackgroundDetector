<?php

declare(strict_types=1);

namespace App\Adapter\Framework\Command;

use App\Application\UseCase\ImageAnalyze\AnalyzeImageBackground;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;

use function count;
use function microtime;
use function number_format;
use function sprintf;

#[AsCommand(
    name: 'image:analyze',
    description: 'Detect white background on images',
)]
class WhiteBackgroundDetector extends Command
{
    private const string VALID_IMAGES_PATH = '/public/images/valid';
    private const string INVALID_IMAGES_PATH = '/public/images/invalid';

    public function __construct(
        private readonly AnalyzeImageBackground $analyzeImage,
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Starting white image background detection..');

        // $this->analyzeValidImages($output, $io);
        $this->analyzeInvalidImages($output, $io);

        return Command::SUCCESS;
    }

    private function analyzeValidImages(OutputInterface $output, SymfonyStyle $io): void
    {
        $validImages = $this->getFilesInDirectory(sprintf('%s%s', $this->kernel->getProjectDir(), self::VALID_IMAGES_PATH));

        $progressBar = $this->getProgressBar($output, $validImages);

        $falsePositives = [];
        $startTime = microtime(true);

        foreach ($validImages as $image) {
            $progressBar->setMessage(sprintf('Processing valid image "%s"', $image), 'filename');
            $progressBar->display();
            $progressBar->advance();

            $hasWhiteBackground = $this->analyzeImage->execute($image);

            if (false === $hasWhiteBackground) {
                $falsePositives[] = $image;
            }
        }

        $progressBar->finish();

        $io->newLine();

        $elapsedTime = microtime(true) - $startTime;

        if (count($falsePositives) === 0) {
            $io->success(sprintf(
                'Analyze completed in %s seconds.All images with white background passed the validation',
                number_format($elapsedTime, 3),
            ));
        } else {
            foreach ($falsePositives as $falsePositive) {
                $io->error(sprintf('Image %s detected as false positive', $falsePositive));
            }

            $io->error(sprintf(
                'Analyze completed in %s seconds. %s detected as false positive',
                number_format($elapsedTime, 3),
                count($falsePositives),
            ));
        }
    }

    private function analyzeInvalidImages(OutputInterface $output, SymfonyStyle $io): void
    {
        $invalidImages = $this->getFilesInDirectory(sprintf('%s%s', $this->kernel->getProjectDir(), self::INVALID_IMAGES_PATH));

        $progressBar = $this->getProgressBar($output, $invalidImages);

        $falseNegatives = [];
        $startTime = microtime(true);

        foreach ($invalidImages as $image) {
            $progressBar->setMessage(sprintf('Processing invalid image "%s"', $image), 'filename');
            $progressBar->display();
            $progressBar->advance();

            $hasWhiteBackground = $this->analyzeImage->execute($image);

            if (true === $hasWhiteBackground) {
                $falseNegatives[] = $image;
            }
        }

        $progressBar->finish();

        $io->newLine();

        $elapsedTime = microtime(true) - $startTime;

        if (count($falseNegatives) === 0) {
            $io->success(sprintf(
                'Analyze completed in %s seconds. All images without white background were rejected',
                number_format($elapsedTime, 3),
            ));
        } else {
            foreach ($falseNegatives as $falseNegative) {
                $io->warning(sprintf('Image %s detected as false negative', $falseNegative));
            }

            $io->error(sprintf(
                'Analyze completed in %s seconds. %s detected as false negative. Rejection rate: %s',
                number_format($elapsedTime, 3),
                count($falseNegatives),
                count($falseNegatives)/count($invalidImages) * 100,
            ));
        }
    }

    private function getFilesInDirectory(string $directory): array
    {
        $finder = new Finder();
        $finder->files()->in($directory);

        $files = [];

        foreach ($finder as $file) {
            $files[] = $file->getRealPath();
        }

        return $files;
    }

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
