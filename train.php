<?php

include __DIR__ . '/vendor/autoload.php';

use Rubix\ML\Other\Loggers\Screen;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\PersistentModel;
use Rubix\ML\Pipeline;
use Rubix\ML\Transformers\ImageResizer;
use Rubix\ML\Transformers\ImageVectorizer;
use Rubix\ML\Transformers\ZScaleStandardizer;
use Rubix\ML\Classifiers\MultilayerPerceptron;
use Rubix\ML\NeuralNet\Layers\Dense;
use Rubix\ML\NeuralNet\Layers\Dropout;
use Rubix\ML\NeuralNet\Layers\Activation;
use Rubix\ML\NeuralNet\ActivationFunctions\LeakyReLU;
use Rubix\ML\NeuralNet\Optimizers\Adam;
use Rubix\ML\Persisters\Filesystem;
use Rubix\ML\Datasets\Unlabeled;

use function Rubix\ML\array_transpose;

ini_set('memory_limit', '-1');

$logger = new Screen();

$logger->info('Loading data into memory');

$samples = $labels = [];

for ($label = 0; $label < 10; $label++) {
    foreach (glob("training/$label/*.png") as $file) {
        $samples[] = [imagecreatefrompng($file)];
        $labels[] = "#$label";
    }
}

$dataset = new Labeled($samples, $labels);

$estimator = new PersistentModel(
    new Pipeline([
        new ImageResizer(28, 28),
        new ImageVectorizer(true),
        new ZScaleStandardizer(),
    ], new MultilayerPerceptron([
        new Dense(100),
        new Activation(new LeakyReLU()),
        new Dropout(0.2),
        new Dense(100),
        new Activation(new LeakyReLU()),
        new Dropout(0.2),
        new Dense(100),
        new Activation(new LeakyReLU()),
        new Dropout(0.2),
    ], 256, new Adam(0.0001))),
    new Filesystem('mnist.model', true)
);

$estimator->setLogger($logger);

$estimator->train($dataset);

$scores = $estimator->scores();
$losses = $estimator->steps();

Unlabeled::build(array_transpose([$scores, $losses]))
    ->toCSV(['scores', 'losses'])
    ->write('progress.csv');

$logger->info('Progress saved to progress.csv');

if (strtolower(trim(readline('Save this model? (y|[n]): '))) === 'y') {
    $estimator->save();
}
