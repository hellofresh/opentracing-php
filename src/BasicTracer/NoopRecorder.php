<?php declare(strict_types=1);

namespace HelloFresh\OpenTracing\BasicTracer;

use HelloFresh\BasicTracer\RecorderInterface;
use HelloFresh\OpenTracing\SpanInterface;

class NoopRecorder implements RecorderInterface
{
    /**
     * @param SpanInterface $span
     */
    public function record(SpanInterface $span)
    {
    }
}
