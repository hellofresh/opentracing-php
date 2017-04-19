<?php declare(strict_types=1);

namespace HelloFresh\BasicTracer\Propagation;

use HelloFresh\OpenTracing\SpanContextInterface;

interface InjectorInterface
{
    /**
     * Inject the span context into a carrier
     *
     * @param SpanContextInterface $spanContext The context to inject
     * @param mixed $carrier The carrier to inject the context into
     * @return mixed The injected carrier
     */
    public function inject(SpanContextInterface $spanContext, $carrier);
}
