<?php declare(strict_types=1);

namespace HelloFresh\OpenTracing;

/**
 * Represents an in-flight span in the opentracing system.
 *
 * Spans are created by the {@see Tracer::buildSpan} interface.
 */
interface SpanInterface
{
    /**
     * Retrieve the associated SpanContext.
     *
     * This may be called at any time, including after calls to finish().
     *
     * @link https://github.com/opentracing/specification/blob/master/specification.md#retrieve-the-spans-spancontext
     *
     * @return SpanContextInterface the SpanContext that encapsulates Span state
     *                              that should propagate across process boundaries.
     */
    public function context() : SpanContextInterface;

    /**
     * Sets the end timestamp to now and records the span.
     *
     * With the exception of calls to {@see Span::context}, this should be the last call made to the span instance, and
     * to do otherwise leads to undefined behavior.
     *
     * @link https://github.com/opentracing/specification/blob/master/specification.md#finish-the-span
     *
     * @return void
     */
    public function finish();

    /**
     * Override the string name for the logical operation this span represents.
     *
     * @link https://github.com/opentracing/specification/blob/master/specification.md#overwrite-the-operation-name
     *
     * @param string $operationName
     * @return SpanInterface this Span instance, for chaining
     */
    public function setOperationName(string $operationName) : SpanInterface;

    /**
     * Set a key:value tag on the Span.
     *
     * @link https://github.com/opentracing/specification/blob/master/specification.md#set-a-span-tag
     *
     * @param string $key
     * @param bool|int|float|string $value Tag value, which must be either a string, a boolean value, or a numeric type
     * @return SpanInterface this Span instance, for chaining
     */
    public function setTag(string $key, $value) : SpanInterface;

    /**
     * Record an event at a specific timestamp.
     *
     * @link https://github.com/opentracing/specification/blob/master/specification.md#log-structured-data
     *
     * @param string $key
     * @param $value
     * @param float|null $timestampMicroseconds The explicit timestamp for the log record or NULL.
     *                                          Must be greater than or equal to the Span's start timestamp.
     * @return SpanInterface the Span, for chaining
     */
    public function log(string $key, $value, float $timestampMicroseconds = null) : SpanInterface;

    /**
     * Record an event at a specific timestamp.
     *
     * @link https://github.com/opentracing/specification/blob/master/specification.md#log-structured-data
     *
     * @param array $fields A map where the keys must be strings and the values may have any type at all.
     * @param float|null $timestampMicroseconds The explicit timestamp for the log record or NULL.
     *                                          Must be greater than or equal to the Span's start timestamp.
     * @return SpanInterface the Span, for chaining
     */
    public function logs(array $fields, float $timestampMicroseconds = null) : SpanInterface;

    /**
     * Sets a baggage item in the Span (and its SpanContext) as a key/value pair.
     *
     * Baggage enables powerful distributed context propagation functionality where arbitrary application data can be
     * carried along the full path of request execution throughout the system.
     *
     * Note 1: Baggage is only propagated to the future (recursive) children of this SpanContext.
     *
     * Note 2: Baggage is sent in-band with every subsequent local and remote calls, so this feature must be used with
     * care.
     *
     * @link https://github.com/opentracing/specification/blob/master/specification.md#set-a-baggage-item
     *
     * @param string $key
     * @param string $value
     * @return SpanInterface this Span instance, for chaining
     */
    public function setBaggageItem(string $key, string $value) : SpanInterface;

    /**
     * Retrieve the value of a baggage item by it's key.
     *
     * @link https://github.com/opentracing/specification/blob/master/specification.md#get-a-baggage-item
     *
     * @param string $key
     * @return null|string the value of the baggage item for given key, or null if no such item could be found
     */
    public function getBaggageItem(string $key);
}
