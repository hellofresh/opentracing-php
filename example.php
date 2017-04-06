<?php declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

// Create cache for auth token
$cache = new \Doctrine\Common\Cache\PhpFileCache(__DIR__ . '/.example-cache');

// Configure Google cloud authenticator
$gCloudAuth = new \HelloFresh\GoogleCloudTracer\AuthProvider(
    getenv('gc_email'),
    getenv('gc_private_id'),
    str_replace('\n', "\n", getenv('gc_private_key')),
    $cache
);

// Create Google cloud recorder
$recorder = new \HelloFresh\GoogleCloudTracer\Recorder(
    $gCloudAuth,
    getenv('gc_project_id')
);

// Create the tracer
$tracer = new \HelloFresh\BasicTracer\BasicTracer($recorder, function () {
    return true;
});

// Do the magic
echo "Start 0\n";
$span = $tracer->startSpan('opentracing-php');

usleep(5000);

$span1 = $tracer->startSpan('opentracing-php-internal', [
    new \HelloFresh\OpenTracing\SpanReference(\HelloFresh\OpenTracing\SpanReference::CHILD_OF, $span->context()),
]);
echo "1\n";
usleep(5000);

$span1->finish();

usleep(5000);

echo "Finish 0\n";
$span->finish();

echo  "Done\n";
