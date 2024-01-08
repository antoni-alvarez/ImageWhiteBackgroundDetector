<?php

declare(strict_types=1);

namespace App\Adapter\Framework\Command\ML;

use Generator;
use Override;
use Rubix\ML\Classifiers\ClassificationTree;
use Rubix\ML\Classifiers\RandomForest;
use Rubix\ML\CrossValidation\Metrics\Accuracy;
use Rubix\ML\CrossValidation\Reports\ConfusionMatrix;
use Rubix\ML\Datasets\Labeled;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

use function array_map;
use function array_pop;
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
    private const DATA_PATH = '/public/data.csv';

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

        $this->train($output, $io);

        return Command::SUCCESS;
    }

    private function train(OutputInterface $output, SymfonyStyle $io): void
    {
        $startTime = microtime(true);

        // TODO Change data file format to NDJSON
        [$trainingSet, $testingSet] = $this->generateDataset()->stratifiedSplit(0.8);

        $classifier = new RandomForest(new ClassificationTree(10), 256, 0.5, true);
        $classifier->train($trainingSet);

        /** @var array<int, string> $predictedLabels */
        $predictedLabels = $classifier->predict($testingSet);

        /** @var array<int, string> $actualLabels */
        $actualLabels = $testingSet->labels();

        $accuracy = new Accuracy();
        $score = $accuracy->score($predictedLabels, $actualLabels);

        if ($output->isVerbose()) {
            $confusionMatrix = (new ConfusionMatrix())->generate($predictedLabels, $actualLabels);
            /** @var array<string, array<string, int>> $matrixArray */
            $matrixArray = $confusionMatrix->toArray();
            $this->printConfusionMatrix($output, $matrixArray);
        }

        $elapsedTime = microtime(true) - $startTime;

        $io->success(sprintf(
            'Model trained in %s seconds. Accuracy: %s',
            $elapsedTime,
            $score,
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

    private function generateDataset(): Labeled
    {
        $csvGenerator = $this->readCsvDataGenerator($this->getDataFile());

        $samples = [];
        $labels = [];

        /** @var array<int, string> $row */
        foreach ($csvGenerator as $row) {
            $labels[] = array_pop($row) ?? '';
            $samples[] = array_map(fn ($value): float => (float) $value, $row);
        }

        return new Labeled($samples, $labels);
    }

    /**
     * @param array<string, array<string, int>> $confusionMatrix
     */
    private function printConfusionMatrix(OutputInterface $output, array $confusionMatrix): void
    {
        $output->writeln('Confusion Matrix:');
        $output->writeln('---------------------');
        $output->writeln(sprintf('| TP: %-3d | FP: %-3d |', $confusionMatrix['valid']['valid'], $confusionMatrix['valid']['invalid']));
        $output->writeln(sprintf('| FN: %-3d | TN: %-3d |', $confusionMatrix['invalid']['valid'], $confusionMatrix['invalid']['invalid']));
        $output->writeln(sprintf('---------------------%s', "\n"));
    }
}
