<?php declare(strict_types=1);

namespace HelloFresh\OpenTracing;

/**
 * SpanContext represents Span state that must propagate to descendant Spans and across process boundaries.
 *
 * SpanContext is logically divided into two pieces: (1) the user-level "Baggage" that propagates across Span
 * boundaries and (2) any Tracer-implementation-specific fields that are needed to identify or otherwise contextualize
 * the associated Span instance (e.g., a <trace_id, span_id, sampled> tuple).
 *
 * @see SpanInterface#setBaggageItem(String, String)
 * @see SpanInterface#getBaggageItem(String)
 */
interface SpanContextInterface
{
    /**
     * @return array all zero or more baggage items propagating along with the associated Span
     *
     * @see SpanInterface::setBaggageItem
     * @see SpanInterface::getBaggageItem
     */
    public function getBaggageItems() : array;
}
