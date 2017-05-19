<?php declare(strict_types=1);

namespace HelloFresh\GoogleCloudTracer;

use HelloFresh\BasicTracer\RecorderInterface;
use HelloFresh\BasicTracer\Span;
use HelloFresh\BasicTracer\SpanContext;
use HelloFresh\GoogleCloudTracer\Client\RecorderClientInterface;
use HelloFresh\GoogleCloudTracer\Formatter\GoogleCloudFormatter;
use HelloFresh\GoogleCloudTracer\Formatter\TraceFormatterInterface;
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
     * @var TraceFormatterInterface
     */
    private $formatter;

    /**
     * @var array
     */
    private $spans = [];

    /**
     * @var string
     */
    private $traceId;

    /**
     * @param RecorderClientInterface $client
     * @param string $projectId
     * @param TraceFormatterInterface|null $formatter
     */
    public function __construct(
        RecorderClientInterface $client,
        string $projectId,
        TraceFormatterInterface $formatter = null
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

        // Resolve the traceId for google cloud
        // Related to https://github.com/hellofresh/gcloud-opentracing/blob/master/recorder.go#L98
        if ($this->traceId === null) {
            $traceId = $context->getTraceId();
            if (strlen($traceId) === 36) {
                $traceId = str_replace('-', '', $traceId);
            }
            if (strlen($traceId) === 16) {
                $traceId .= $traceId;
            }

            $this->traceId = $traceId;
        }

        $this->spans[] = $this->formatter->formatSpan($span);
    }

    /**
     * Send all spans at once
     */
    public function commit()
    {
        $this->client->patchTraces(
            $this->formatter->formatTrace($this->projectId, $this->traceId, $this->spans)
        );
    }
}
