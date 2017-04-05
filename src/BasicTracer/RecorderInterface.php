<?php declare(strict_types=1);

namespace HelloFresh\BasicTracer;

use HelloFresh\OpenTracing\SpanInterface;

interface RecorderInterface
{
    public function record(SpanInterface $span);
}
