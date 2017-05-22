<?php declare(strict_types=1);

namespace HelloFresh\GoogleCloudTracer;

use HelloFresh\BasicTracer\RecorderInterface;
use HelloFresh\BasicTracer\Span;
use HelloFresh\BasicTracer\SpanContext;
use HelloFresh\GoogleCloudTracer\Client\RecorderClientInterface;
use HelloFresh\GoogleCloudTracer\Formatter\GoogleCloudFormatter;
use HelloFresh\OpenTracing\SpanInterface;

/**
 * @link https://github.com/google/google-api-php-client-services/tree/master/src/Google/Service/CloudTrace
 */
class Recorder implements RecorderInterface
{
    /**
     * @var string
     */
    private $projectId;

    /**
     * @var RecorderClientInterface
     */
    private $client;

    /**
     * @var GoogleCloudFormatter
     */
    private $formatter;

    /**
     * @param RecorderClientInterface $client
     * @param string $projectId
     * @param GoogleCloudFormatter|null $formatter
     */
    public function __construct(
        RecorderClientInterface $client,
        string $projectId,
        GoogleCloudFormatter $formatter = null
    ) {
        if ($formatter === null) {
            $formatter = new GoogleCloudFormatter();
        }

        $this->client = $client;
        $this->projectId = $projectId;
        $this->formatter = $formatter;
    }

    /**
     * @inheritdoc
     */
    public function record(SpanInterface $span)
    {
        $context = $span->context();
        if (!$span instanceof Span || !$context instanceof SpanContext) {
            return;
        }

        // We are not sampling this trace to ignore it
        if (!$context->isSampled()) {
            return;
        }

        // Send the request
        $this->client->patchTraces(
            [
                $this->formatter->formatTrace($this->projectId, $context->getTraceId(), [$span]),
            ]
        );
    }
}
