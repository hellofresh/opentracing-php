<?php declare(strict_types=1);

namespace HelloFresh\GoogleCloudTracer\Client;

interface RecorderClientInterface
{
    /**
     * @link https://cloud.google.com/trace/docs/reference/v1/rest/v1/projects/patchTraces
     *
     * @param array $traces
     *
     * @return bool
     */
    public function patchTraces(array $traces) : bool;
}
