<?php declare(strict_types=1);

namespace HelloFresh\GoogleCloudTracer\Formatter;

use HelloFresh\OpenTracing\SpanInterface;

interface TraceFormatterInterface
{
    /**
     * Formats a trace to an arbitrary representation
     *
     * @param string $projectId
     * @param string $traceId
     * @param SpanInterface[]|array $spans
     *
     * @return mixed
     */
    public function formatTrace(string $projectId, string $traceId, array $spans);

    /**
     * Formats a span to an arbitrary representation
     *
     * @param SpanInterface $span
     *
     * @return mixed
     */
    public function formatSpan(SpanInterface $span);
}
