<?php declare(strict_types=1);

namespace HelloFresh\BasicTracer;

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
