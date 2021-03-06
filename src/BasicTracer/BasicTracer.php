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
use Moontoast\Math\BigNumber;
use function random_bytes;

class BasicTracer implements TracerInterface
{
    /**
     * @var NoopRecorder|null
     */
    private static $noopRecorder;

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
            $number = BigNumber::convertToBase10($traceId, 16);
            $number = new BigNumber($number);

            return $number->mod(64)->isEqualTo(0);
        };
    }

    /**
     * @return RecorderInterface
     */
    protected static function getNoopRecorder() : RecorderInterface
    {
        if (self::$noopRecorder === null) {
            self::$noopRecorder = new NoopRecorder();
        }

        return self::$noopRecorder;
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
            $traceId = $this->generateTraceId();
            $context = new SpanContext(
                $traceId,
                $spanId,
                (bool) ($this->shouldSample)($traceId)
            );
        }

        // Use the recorder only when the trace is sampled
        $recorder = $context->isSampled() ? $this->recorder : static::getNoopRecorder();

        // Create the Span (not to be confused with SPAM)
        $span = new Span(
            $recorder,
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

    private function generateTraceId() : string
    {
        return bin2hex(random_bytes(8));
    }
}
