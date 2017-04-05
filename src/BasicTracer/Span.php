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
     * @var int
     */
    private $startTimestamp;
    /**
     * @var int
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
     * @param int $startTimestamp
     * @param string $operationName
     * @param int $parentSpanId
     * @param SpanContext $initialContext
     */
    public function __construct(
        RecorderInterface $recorder,
        int $startTimestamp,
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
     * @return int
     */
    public function getStartTimestamp() : int
    {
        return $this->startTimestamp;
    }

    /**
     * @return int
     */
    public function getEndTimestamp() : int
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
        $this->endTimestamp = (int) ($this->endTimestamp ?: microtime(false));

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
    public function log($event, int $timestampMicroseconds = null) : SpanInterface
    {
        $this->assertUnfinished();

        $this->logs[] = [$event, $timestampMicroseconds ?: microtime(false)];

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
