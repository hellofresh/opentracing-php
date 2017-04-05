<?php declare(strict_types=1);

namespace HelloFresh\BasicTracer\Propagation;

use HelloFresh\OpenTracing\SpanContextInterface;

interface InjectorInterface
{
    public function inject(SpanContextInterface $spanContext, $carrier);
}
