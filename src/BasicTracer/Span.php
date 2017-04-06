<?php declare(strict_types=1);

namespace HelloFresh\BasicTracer;

use HelloFresh\OpenTracing\SpanContextInterface;
use HelloFresh\OpenTracing\SpanInterface;

class Span implements SpanInterface
{
    /**
     * @var RecorderInterface
     */
    private $recorder;
    /**
     * @var float
     */
    private $startTimestamp;
    /**
     * @var float
     */
    private $endTimestamp;
    /**
     * @var string
     */
    private $operationName;
    /**
     * @var SpanContext
     */
    private $initialContext;
    /**
     * @var array
     */
    private $baggage;
    /**
     * @var array
     */
    private $tags = [];
    /**
     * @var array
     */
    private $logs = [];
    /**
     * @var int
     */
    private $parentSpanId;

    /**
     * @param RecorderInterface $recorder
     * @param float $startTimestamp
     * @param string $operationName
     * @param int $parentSpanId
     * @param SpanContext $initialContext
     */
    public function __construct(
        RecorderInterface $recorder,
        float $startTimestamp,
        string $operationName,
        SpanContext $initialContext,
        int $parentSpanId = null
    ) {
        $this->recorder = $recorder;
        $this->startTimestamp = $startTimestamp;
        $this->operationName = $operationName;
        $this->initialContext = $initialContext;
        $this->baggage = $initialContext->getBaggageItems();
        $this->parentSpanId = $parentSpanId;
    }

    /**
     * @inheritdoc
     */
    public function context() : SpanContextInterface
    {
        return new SpanContext(
            $this->initialContext->getTraceId(),
            $this->initialContext->getSpanId(),
            $this->initialContext->isSampled(),
            $this->baggage
        );
    }

    /**
     * @return float
     */
    public function getStartTimestamp() : float
    {
        return $this->startTimestamp;
    }

    /**
     * @return int
     */
    public function getEndTimestamp() : float
    {
        return $this->endTimestamp;
    }

    /**
     * @return string
     */
    public function getOperationName() : string
    {
        return $this->operationName;
    }

    /**
     * @return int|null
     */
    public function getParentSpanId()
    {
        return $this->parentSpanId;
    }

    /**
     * @inheritdoc
     */
    public function finish()
    {
        if ($this->recorder === null) {
            return;
        }

        $this->endTimestamp = $this->endTimestamp ?: microtime(true);

        $this->recorder->record($this);
        $this->recorder = null;
    }

    /**
     * @inheritdoc
     */
    public function setOperationName(string $operationName) : SpanInterface
    {
        $this->assertUnfinished();

        $this->operationName = $operationName;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setTag(string $key, $value) : SpanInterface
    {
        $this->assertUnfinished();

        $this->tags[$key] = $value;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function log($event, float $timestampMicroseconds = null) : SpanInterface
    {
        $this->assertUnfinished();

        $this->logs[] = [$event, $timestampMicroseconds ?: microtime(true)];

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setBaggageItem(string $key, string $value) : SpanInterface
    {
        $this->assertUnfinished();

        $this->baggage[$key] = $value;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getBaggageItem(string $key)
    {
        return $this->baggage[$key] ?? null;
    }

    /**
     * Go bare metal when the span is finished
     */
    private function assertUnfinished()
    {
        if ($this->recorder === null) {
            throw new \LogicException('You are the destroyer of worlds! Maybe it\'s time to go to bed?');
        }
    }
}
