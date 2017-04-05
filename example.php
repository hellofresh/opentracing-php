<?php declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

// Configure Google cloud
$gCloudConfig = new \HelloFresh\GoogleCloudTracer\Config(
    getenv('gc_email'),
    str_replace('\n', "\n", getenv('gc_private_key')),
    getenv('gc_project_id')
);

$client = \Http\Discovery\HttpAsyncClientDiscovery::find();
$recorder = new \HelloFresh\GoogleCloudTracer\Recorder(
    $gCloudConfig,
    $client,
    \Http\Discovery\MessageFactoryDiscovery::find(),
    \Http\Discovery\UriFactoryDiscovery::find()
);

// Create the tracer
$tracer = new \HelloFresh\BasicTracer\BasicTracer($recorder, function () {
    return true;
});

// Do the magic
$span = $tracer->startSpan('opentracing-php');

$span1 = $tracer->startSpan('opentracing-php/span1', [
    new \HelloFresh\OpenTracing\SpanReference(\HelloFresh\OpenTracing\SpanReference::CHILD_OF, $span->context()),
]);

echo "Action one\n";

$span1->finish();

echo  "Let's Sleep\n";
sleep(3);

$span->finish();

echo  "Done so let's wait\n";
sleep(3);
