<?php declare(strict_types=1);

namespace HelloFresh\GoogleCloudTracer\Client;

interface ClientInterface
{
    /**
     * @link https://cloud.google.com/trace/docs/reference/v1/rest/v1/projects/patchTraces
     *
     * @param string $traceJson JSON payload which is going to be sent to Google Cloud Traces
     *
     * @return bool
     */
    public function patchTraces(string $traceJson) : bool;
}
