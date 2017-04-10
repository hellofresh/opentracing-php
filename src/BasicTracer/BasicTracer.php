<?php declare(strict_types=1);

namespace HelloFresh\BasicTracer;

use HelloFresh\BasicTracer\Exception\ExtractionException;
use HelloFresh\BasicTracer\Exception\InjectionException;
use HelloFresh\BasicTracer\Propagation\ExtractorInterface;
use HelloFresh\BasicTracer\Propagation\InjectorInterface;
use HelloFresh\OpenTracing\SpanContextInterface;
use HelloFresh\OpenTracing\SpanInterface;
use HelloFresh\OpenTracing\SpanReference;
use HelloFresh\OpenTracing\TracerInterface;
use Ramsey\Uuid\Uuid;

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
        $this->shouldSample = $shouldSample ?: function (string $traceId) {
            /** @var \Moontoast\Math\BigNumber $int */
            // https://github.com/opentracing/basictracer-go/blob/1b32af207119a14b1b231d451df3ed04a72efebf/tracer.go#L100
            $int = Uuid::fromString($traceId)->getInteger();

            return $int->mod(64)->isEqualTo(0);
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
            if (in_array($reference->getType(), [SpanReference::CHILD_OF, SpanReference::FOLLOWS_FROM], true)) {
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
            $traceId = Uuid::uuid4()->getHex();
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
            $context,
            $parentSpanId
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
            throw new InjectionException(sprintf('No injector is available for format \'%s\'', $format));
        }

        return $this->injectors[$format]->inject($spanContext, $carrier);
    }

    /**
     * @inheritdoc
     */
    public function extract(string $format, $carrier) : SpanContextInterface
    {
        if (!isset($this->extractors[$format])) {
            throw new ExtractionException(sprintf('No extractor is available for format \'%s\'', $format));
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
