<?php declare(strict_types=1);

namespace HelloFresh\BasicTracer;

use HelloFresh\BasicTracer\Propagation\ExtractorInterface;
use HelloFresh\BasicTracer\Propagation\InjectorInterface;
use HelloFresh\OpenTracing\SpanContextInterface;
use HelloFresh\OpenTracing\SpanInterface;
use HelloFresh\OpenTracing\SpanReference;
use HelloFresh\OpenTracing\TracerInterface;

class BasicTracer implements TracerInterface
{
    /**
     * @var RecorderInterface
     */
    private $recorder;

    /**
     * @var \Closure
     */
    private $shouldSample;

    /**
     * @var InjectorInterface[]
     */
    private $injectors = [];

    /**
     * @var ExtractorInterface[]
     */
    private $extractors = [];

    public function __construct(RecorderInterface $recorder, \Closure $shouldSample = null)
    {
        $this->recorder = $recorder;
        $this->shouldSample = $shouldSample ?: function (int $traceId) {
            return $traceId % 64 == 0;
        };
    }

    /**
     * @inheritdoc
     */
    public function startSpan(
        string $operationName,
        array $references = [],
        float $startTimestamp = null,
        array $tags = []
    ) : SpanInterface {
        $startTimestamp = $startTimestamp ?: microtime(true);
        $spanId = mt_rand();

        // Resolve base context
        $context = $parentSpanId = null;
        foreach ($references as $reference) {
            if (in_array($reference->getType(), [SpanReference::CHILD_OF. SpanReference::FOLLOWS_FROM], true)) {
                /** @var SpanContext $parentContext */
                $parentContext = $reference->getContext();

                $context = new SpanContext(
                    $parentContext->getTraceId(),
                    $spanId,
                    $parentContext->isSampled(),
                    $parentContext->getBaggageItems()
                );
                $parentSpanId = $parentContext->getSpanId();
                break;
            }
        }
        if ($context === null) {
            $traceId = mt_rand();
            $context = new SpanContext(
                $traceId,
                $spanId,
                (bool) ($this->shouldSample)($traceId)
            );
        }

        // Create the Span (not to be confused with SPAM)
        $span = new Span(
            $this->recorder,
            $startTimestamp,
            $operationName,
            $context
        );
        foreach ($tags as $key => $val) {
            $span->setTag($key, $val);
        }

        return $span;
    }

    /**
     * @inheritdoc
     */
    public function inject(SpanContextInterface $spanContext, string $format, $carrier)
    {
        if (!isset($this->injectors[$format])) {
            throw new \RuntimeException();
        }

        return $this->injectors[$format]->inject($spanContext, $carrier);
    }

    /**
     * @inheritdoc
     */
    public function extract(string $format, $carrier) : SpanContextInterface
    {
        if (!isset($this->extractors[$format])) {
            throw new \RuntimeException();
        }

        return $this->extractors[$format]->extract($carrier);
    }

    public function registerInjector(string $format, InjectorInterface $injector) : BasicTracer
    {
        $this->injectors[$format] = $injector;

        return $this;
    }

    public function registerExtractor(string $format, ExtractorInterface $extractor) : BasicTracer
    {
        $this->extractors[$format] = $extractor;

        return $this;
    }
}
