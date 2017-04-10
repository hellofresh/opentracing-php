<?php declare(strict_types=1);

namespace HelloFresh\BasicTracer\Propagation;

use HelloFresh\OpenTracing\SpanContextInterface;

interface ExtractorInterface
{
    public function extract($carrier) : SpanContextInterface;
}
