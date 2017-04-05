<?php declare(strict_types=1);

namespace HelloFresh\OpenTracing;

/**
 * Tracer is a simple, thin interface for Span creation and propagation across arbitrary transports.
 **/
interface TracerInterface
{
    const FORMAT_TEXT_MAP = 'TEXT_MAP';
    const FORMAT_HTTP_HEADERS = 'HTTP_HEADERS';
    const FORMAT_BINARY = 'BINARY';

    /**
     * Return a new SpanBuilder for a Span with the given `operationName`.
     *
     * You can override the operationName later via {@see Span::setOperationName}.
     *
     * @param string $operationName
     * @param SpanReference[] $references
     * @param int|null $startTimestamp a explicit start time in microseconds or current time when omitted
     * @param array $tags
     *
     * @return SpanInterface
     */
    public function startSpan(
        string $operationName,
        array $references = [],
        int $startTimestamp = null,
        array $tags = []
    ) : SpanInterface;

    /**
     * Inject a SpanContext into a `carrier` of a given type, presumably for propagation across process boundaries.
     *
     * @param SpanContextInterface $spanContext A SpanContext instance
     * @param string $format A format descriptor (typically but not necessarily a string constant) which tells
     *                       the Tracer implementation how to encode the SpanContext in the carrier parameter
     * @param mixed $carrier A carrier, whose type is dictated by the format. The Tracer implementation will encode
     *                       the SpanContext in this carrier object according to the format.
     *
     * @return mixed the carrier with the injected information
     */
    public function inject(SpanContextInterface $spanContext, string $format, $carrier);

    /**
     * Extract a SpanContext from a `carrier` of a given type, presumably after propagation across a process boundary.
     *
     * @param string $format A format descriptor which tells the Tracer implementation how to decode SpanContext from
     *                       the carrier parameter
     * @param mixed $carrier A carrier, whose type is dictated by the format. The Tracer implementation will decode the
     *                       SpanContext from this carrier object according to format.
     *
     * @return SpanContextInterface instance suitable for use as a reference when starting a new Span via the Tracer.
     */
    public function extract(string $format, $carrier) : SpanContextInterface;
}
