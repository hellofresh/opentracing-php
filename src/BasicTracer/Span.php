<?php declare(strict_types=1);

namespace HelloFresh\BasicTracer;

use HelloFresh\BasicTracer\Exception\SpanStateException;
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
    private $context;
    /**
     * @var array
     */
    private $baggage;
    /**
     * @var bool
     */
    private $baggageDirty;
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
     * @param SpanContext $context
     */
    public function __construct(
        RecorderInterface $recorder,
        float $startTimestamp,
        string $operationName,
        SpanContext $context,
        int $parentSpanId = null
    ) {
        $this->recorder = $recorder;
        $this->startTimestamp = $startTimestamp;
        $this->operationName = $operationName;
        $this->context = $context;
        $this->baggage = $context->getBaggageItems();
        $this->baggageDirty = false;
        $this->parentSpanId = $parentSpanId;
    }

    /**
     * @inheritdoc
     */
    public function context() : SpanContextInterface
    {
        if ($this->baggageDirty) {
            $this->context = new SpanContext(
                $this->context->getTraceId(),
                $this->context->getSpanId(),
                $this->context->isSampled(),
                $this->baggage
            );
        }

        return $this->context;
    }

    /**
     * @return array
     */
    public function getTags() : array
    {
        return $this->tags;
    }

    /**
     * @return float
     */
    public function getStartTimestamp() : float
    {
        return $this->startTimestamp;
    }

    /**
     * @return float|null
     */
    public function getEndTimestamp()
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
     * @return array A map where the keys are strings and the values array with the first element being the value of
     *               any type and the second being the timestamp or NULL if none was logged.
     */
    public function getLogs() : array
    {
        return $this->logs;
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
    public function log(string $key, $value, float $timestampMicroseconds = null) : SpanInterface
    {
        $this->assertUnfinished();

        $this->logs[$key][] = [$value, $timestampMicroseconds];

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function logs(array $fields, float $timestampMicroseconds = null) : SpanInterface
    {
        foreach ($fields as $key => $value) {
            $this->log($key, $value, $timestampMicroseconds);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setBaggageItem(string $key, string $value) : SpanInterface
    {
        $this->assertUnfinished();

        $this->baggage[$key] = $value;
        $this->baggageDirty = true;

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
            throw new SpanStateException(
                'The Span is finished! You have become the destroyer of worlds! Maybe it\'s time to go to bed?'
            );
        }
    }
}
