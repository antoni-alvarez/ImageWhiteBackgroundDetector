<?php

declare(strict_types=1);

namespace App\Adapter\Framework\Command;

use Generator;
use Override;
use Phpml\Classification\KNearestNeighbors;
use Phpml\CrossValidation\StratifiedRandomSplit;
use Phpml\Dataset\ArrayDataset;
use Phpml\Metric\Accuracy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

use function array_shift;
use function fclose;
use function fgetcsv;
use function fopen;
use function microtime;
use function sprintf;

#[AsCommand(
    name: 'image:train',
    description: 'Train machine learning model for white background detector',
)]
class TrainMLBackgroundDetector extends Command
{
    private const string DATA_PATH = '/public/data.csv';

    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Starting ML white background detector training..');

        $this->train($io);

        return Command::SUCCESS;
    }

    private function train(SymfonyStyle $io): void
    {
        $startTime = microtime(true);

        $dataset = $this->generateDataset();

        $split = new StratifiedRandomSplit($dataset, 0.001);
        $trainingSet = $split->getTrainSamples();
        $trainingLabels = $split->getTrainLabels();
        $testingSet = $split->getTestSamples();
        $actualLabels = $split->getTestLabels();

        $classifier = new KNearestNeighbors();
        $classifier->train($trainingSet, $trainingLabels);

        /** @var array<int, int> $predictedLabels */
        $predictedLabels = $classifier->predict($testingSet);

        $accuracy = Accuracy::score($predictedLabels, $actualLabels);

        $elapsedTime = microtime(true) - $startTime;

        $io->success(sprintf(
            'Model trained in %s seconds. Accuracy: %s',
            $elapsedTime,
            $accuracy,
        ));
    }

    private function getDataFile(): string
    {
        return sprintf(
            '%s%s',
            $this->kernel->getProjectDir(),
            self::DATA_PATH,
        );
    }

    private function readCsvDataGenerator(string $csvFile): Generator
    {
        if (($handle = fopen($csvFile, 'r')) !== false) {
            while (($row = fgetcsv($handle)) !== false) {
                yield $row;
            }
            fclose($handle);
        }
    }

    private function generateDataset(): ArrayDataset
    {
        $csvGenerator = $this->readCsvDataGenerator($this->getDataFile());

        $samples = [];
        $labels = [];

        /** @var array<int, string> $row */
        foreach ($csvGenerator as $row) {
            $labels[] = array_shift($row);
            $samples[] = $row;
        }

        return new ArrayDataset($samples, $labels);
    }
}
