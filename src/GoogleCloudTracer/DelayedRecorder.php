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
class DelayedRecorder implements RecorderInterface
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
     * @var array Array of spans grouped by TraceId
     */
    private $spans = [];

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

        $this->spans[$context->getTraceId()][] = $this->formatter->formatSpan($span);
    }

    /**
     * Send all traces at once
     *
     * You can hook up this function to register_shutdown_function or listen to process events SIGINT, SIGTERM
     *
     * @see http://php.net/manual/en/function.register-shutdown-function.php
     * @see http://php.net/manual/en/function.register-shutdown-function.php#26251
     */
    public function commit()
    {
        if (empty($this->spans)) {
            return;
        }

        $traces = [];
        foreach ($this->spans as $traceId => $spans) {
            $traces[] = $this->formatter->formatTrace($this->projectId, $traceId, $spans);
        }

        $this->client->patchTraces(
            $traces
        );
    }
}
