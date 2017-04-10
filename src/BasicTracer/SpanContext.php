<?php declare(strict_types=1);

namespace HelloFresh\BasicTracer;

use HelloFresh\OpenTracing\SpanContextInterface;

/**
 * https://github.com/opentracing/basictracer-go/blob/master/context.go#L4
 * traceId = https://github.com/opentracing/basictracer-go/blob/c7c0202a8a77f658aeb2193a27b6c0cfcc821038/util.go#L24
 * seededIDGen = rand.New(rand.NewSource(time.Now().UnixNano()))
 *
 * mt_srand(microtime(false))
 * mt_rand()
 */
class SpanContext implements SpanContextInterface
{
    /**
     * A probabilistically unique identifier for a [multi-span] trace.
     * @var int
     */
    private $traceId;

    /**
     * A probabilistically unique identifier for a span.
     * @var int
     */
    private $spanId;

    /**
     * Whether the trace is sampled.
     * @var bool
     */
    private $sampled;

    /**
     * The span's associated baggage.
     * @var array
     */
    private $baggage;

    /**
     * @param string $traceId A hex representation of a UUID
     * @param int $spanId
     * @param bool $sampled
     * @param array $baggage
     */
    public function __construct(string $traceId, int $spanId, bool $sampled, array $baggage = [])
    {
        $this->traceId = $traceId;
        $this->spanId = $spanId;
        $this->sampled = $sampled;
        $this->baggage = $baggage;
    }

    /**
     * @return string
     */
    public function getTraceId() : string
    {
        return $this->traceId;
    }

    /**
     * @return int
     */
    public function getSpanId() : int
    {
        return $this->spanId;
    }

    /**
     * @return bool
     */
    public function isSampled() : bool
    {
        return $this->sampled;
    }

    /**
     * @inheritdoc
     */
    public function getBaggageItems() : array
    {
        return $this->baggage;
    }
}
