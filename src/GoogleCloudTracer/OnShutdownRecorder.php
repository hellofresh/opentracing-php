<?php declare(strict_types=1);

namespace HelloFresh\GoogleCloudTracer;

use HelloFresh\GoogleCloudTracer\Client\RecorderClientInterface;
use HelloFresh\GoogleCloudTracer\Formatter\TraceFormatterInterface;

/**
 * @link https://github.com/google/google-api-php-client-services/tree/master/src/Google/Service/CloudTrace
 */
class OnShutdownRecorder extends DelayedRecorder
{
    /**
     * @param RecorderClientInterface $client
     * @param string $projectId
     * @param TraceFormatterInterface|null $formatter
     */
    public function __construct(
        RecorderClientInterface $client,
        string $projectId,
        TraceFormatterInterface $formatter = null
    ) {
        parent::__construct($client, $projectId, $formatter);

        register_shutdown_function(function () {
            $this->commit();
        });
    }
}
