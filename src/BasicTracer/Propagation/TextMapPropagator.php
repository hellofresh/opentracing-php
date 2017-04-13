<?php declare(strict_types=1);

namespace HelloFresh\BasicTracer\Propagation;

use HelloFresh\BasicTracer\Exception\ExtractionException;
use HelloFresh\BasicTracer\Exception\InjectionException;
use HelloFresh\BasicTracer\SpanContext;
use HelloFresh\OpenTracing\SpanContextInterface;

/**
 * Based on array or \ArrayAccess
 *
 * https://github.com/opentracing/basictracer-go/blob/c7c0202a8a77f658aeb2193a27b6c0cfcc821038/propagation_ot.go#L22
 */
class TextMapPropagator implements ExtractorInterface, InjectorInterface
{
    const PREFIX_TRACER_STATE = 'ot-tracer-';
    const PREFIX_BAGGAGE = 'ot-baggage-';

    const FIELD_TRACE_ID = self::PREFIX_TRACER_STATE . 'traceid';
    const FIELD_SPAN_ID = self::PREFIX_TRACER_STATE . 'spanid';
    const FIELD_SAMPLED = self::PREFIX_TRACER_STATE . 'sampled';

    public function extract($carrier) : SpanContextInterface
    {
        if (!is_array($carrier) && !$carrier instanceof \Traversable) {
            throw new ExtractionException(sprintf(
                'Unsupported carrier of type "%s" expected an array or object implementing Traversable',
                is_object($carrier) ? get_class($carrier) : gettype($carrier)
            ));
        }

        $traceId = $spanId = $sampled = null;
        $baggage = [];
        foreach ($carrier as $key => $value) {
            $keyLower = mb_strtolower($key);
            switch ($keyLower) {
                case self::FIELD_TRACE_ID:
                    $traceId = (string) $value;
                    break;
                case self::FIELD_SPAN_ID:
                    $spanId = (int) $value;
                    break;
                case self::FIELD_SAMPLED:
                    $sampled = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case strpos($keyLower, self::PREFIX_BAGGAGE) === 0:
                    $key = substr($key, strlen(self::PREFIX_BAGGAGE));
                    $baggage[$key] = $value;
                    break;
            }
        }

        if ($traceId === null || $spanId === null || $sampled === null) {
            throw new ExtractionException('Unable to extract span context from carrier');
        }

        return new SpanContext($traceId, $spanId, $sampled, $baggage);
    }

    public function inject(SpanContextInterface $spanContext, $carrier)
    {
        if (!$spanContext instanceof SpanContext) {
            throw new InjectionException(sprintf('Unsupported SpanContext of type \'%s\'', get_class($spanContext)));
        }

        if (!is_array($carrier) && !$carrier instanceof \ArrayAccess) {
            throw new ExtractionException(sprintf(
                'Unsupported carrier of type "%s" expected an array or object implementing ArrayAccess',
                is_object($carrier) ? get_class($carrier) : gettype($carrier)
            ));
        }

        $carrier[self::FIELD_TRACE_ID] = $spanContext->getTraceId();
        $carrier[self::FIELD_SPAN_ID] = $spanContext->getSpanId();
        $carrier[self::FIELD_SAMPLED] = $spanContext->isSampled();

        foreach ($spanContext->getBaggageItems() as $key => $value) {
            $carrier[self::PREFIX_BAGGAGE . $key] = $value;
        }

        return $carrier;
    }
}
