<?php

declare(strict_types=1);

namespace App\Adapter\Framework\Command;

use App\Application\UseCase\Preprocess\ImagePreprocess;
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

use function count;
use function microtime;
use function number_format;
use function sprintf;

#[AsCommand(
    name: 'image:preprocess',
    description: 'Preprocess images for machine learning background detector',
)]
class ImageMLPreprocess extends Command
{
    private const string VALID_IMAGES_PATH = '/public/images/valid';
    private const string INVALID_IMAGES_PATH = '/public/images/invalid';

    public function __construct(
        private readonly ImagePreprocess $imagePreprocess,
        private readonly KernelInterface $kernel,
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    public function removePreviousProcessedImages(): void
    {
        $processedPath = sprintf(
            '%s%s%s',
            $this->kernel->getProjectDir(),
            self::INVALID_IMAGES_PATH,
            '/processed',
        );

        $this->filesystem->remove($processedPath);
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addOption('valid', mode: InputOption::VALUE_NONE);
        $this->addOption('invalid', mode: InputOption::VALUE_NONE);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Starting valid image background detection..');

        $valid = $input->getOption('valid');
        $invalid = $input->getOption('invalid');
        $both = !$valid && !$invalid;

        if ($valid || $both) {
            $this->preprocessValidImages($output, $io);
        }

        if ($invalid || $both) {
            $this->preprocessInvalidImages($output, $io);
        }

        return Command::SUCCESS;
    }

    private function preprocessValidImages(OutputInterface $output, SymfonyStyle $io): void
    {
        $processedPath = sprintf(
            '%s%s%s',
            $this->kernel->getProjectDir(),
            self::VALID_IMAGES_PATH,
            '/processed',
        );

        $this->filesystem->remove($processedPath);

        $validImages = $this->getFilesInDirectory(self::VALID_IMAGES_PATH);

        $progressBar = $this->getProgressBar($output, $validImages);

        $startTime = microtime(true);

        foreach ($validImages as $image) {
            $progressBar->setMessage(sprintf('Processing valid image "%s"', $image), 'filename');
            $progressBar->display();
            $progressBar->advance();

            $this->imagePreprocess->execute($image);
        }

        $progressBar->finish();

        $io->newLine();

        $elapsedTime = microtime(true) - $startTime;

        $io->success(sprintf(
            'Preprocessed %s valid images in %s seconds.',
            count($validImages),
            number_format($elapsedTime, 3),
        ));
    }

    private function preprocessInvalidImages(OutputInterface $output, SymfonyStyle $io): void
    {
        $this->removePreviousProcessedImages();

        $invalidImages = $this->getFilesInDirectory(self::INVALID_IMAGES_PATH);

        $progressBar = $this->getProgressBar($output, $invalidImages);

        $startTime = microtime(true);

        foreach ($invalidImages as $image) {
            $progressBar->setMessage(sprintf('Processing invalid image "%s"', $image), 'filename');
            $progressBar->display();
            $progressBar->advance();

            $this->imagePreprocess->execute($image);
        }

        $progressBar->finish();

        $io->newLine();

        $elapsedTime = microtime(true) - $startTime;

        $io->success(sprintf(
            'Preprocessed %s invalid images in %s seconds.',
            count($invalidImages),
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
}
