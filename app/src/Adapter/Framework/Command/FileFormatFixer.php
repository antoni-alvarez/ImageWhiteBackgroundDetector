<?php

declare(strict_types=1);

namespace App\Adapter\Framework\Command;

use App\Application\UseCase\ImageAnalyze\FixImageFormat;
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
    name: 'image:fix-format',
    description: 'Fix image file format.',
)]
class FileFormatFixer extends Command
{
    public function __construct(
        private readonly FixImageFormat $fixImageFormat,
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Starting fix image format..');

        $images = $this->getFilesInDirectory(sprintf('%s/public/images', $this->kernel->getProjectDir()));

        $progressBar = new ProgressBar($output, count($images));
        $progressBar->setBarCharacter('<fg=green;options=bold>■</>');
        $progressBar->setProgressCharacter('<fg=green;options=bold>➤</>');
        $progressBar->setEmptyBarCharacter('<fg=red>-</>');

        $progressBar->setFormat(
            "<fg=white;options=bold>%filename%</>\n%current%/%max% [<fg=blue;options=bold>%bar%</>] %percent:3s%%\n⏳%remaining:8s% remaining %memory:21s% \n",
        );

        $startTime = microtime(true);

        $imagesFixed = 0;

        foreach ($images as $image) {
            $progressBar->setMessage(sprintf('Fixing image "%s"', $image), 'filename');
            $progressBar->display();
            $progressBar->advance();

            if ($this->fixImageFormat->execute($image)) {
                $imagesFixed++;
            }
        }

        $progressBar->finish();

        $io->newLine();

        $elapsedTime = microtime(true) - $startTime;

        $io->success(sprintf(
            'Process completed in %s seconds. Images file names fixed: %s',
            number_format($elapsedTime, 3),
            $imagesFixed,
        ));

        return Command::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
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
}
