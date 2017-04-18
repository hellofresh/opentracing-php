<?php declare(strict_types=1);

namespace HelloFresh\BasicTracer\Propagation;

use HelloFresh\OpenTracing\SpanContextInterface;

interface ExtractorInterface
{
    /**
     * Extract a span context form a carrier.
     *
     * @param mixed $carrier
     * @return SpanContextInterface
     */
    public function extract($carrier) : SpanContextInterface;
}
