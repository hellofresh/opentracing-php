<?php declare(strict_types=1);

namespace HelloFresh\GoogleCloudTracer;

use HelloFresh\BasicTracer\RecorderInterface;
use HelloFresh\BasicTracer\Span;
use HelloFresh\BasicTracer\SpanContext;
use HelloFresh\OpenTracing\SpanInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessUtils;

/**
 * @link https://cloud.google.com/trace/docs/reference/v1/rest/v1/projects/patchTraces
 * @link https://github.com/google/google-api-php-client-services/tree/master/src/Google/Service/CloudTrace
 */
class Recorder implements RecorderInterface
{
    /**
     * @var AuthProviderInterface
     */
    private $authHandler;
    /**
     * @var string
     */
    private $projectId;
    /**
     * @var string
     */
    private $projectUrl;
    /**
     * @var array
     */
    private $traces = [];

    /**
     * @param AuthProviderInterface $authHandler
     * @param string $projectId
     */
    public function __construct(AuthProviderInterface $authHandler, string $projectId)
    {
        $this->authHandler = $authHandler;
        $this->projectId = $projectId;
        $this->projectUrl = sprintf(
            'https://cloudtrace.googleapis.com/v1/projects/%s/traces',
            urlencode($this->projectId)
        );
        register_shutdown_function([$this, 'upload']);
    }

    /**
     * @inheritdoc
     */
    public function record(SpanInterface $span)
    {
        $context = $span->context();
        if (!$span instanceof Span || !$context instanceof SpanContext) {
            // TODO?
            return;
        }

        // We are not sampling this trace to ignore it
        if (!$context->isSampled()) {
            return;
        }

        // TODO https://github.com/GoogleCloudPlatform/google-cloud-go/blob/c1a56f8287e26a1e1c9f9b9a979cdf4e5a29c937/trace/trace.go#L535

        // https://cloud.google.com/trace/docs/reference/v1/rest/v1/projects.traces#TraceSpan
        $traceSpan = [
            'spanId' => (string) $context->getSpanId(),
//            'kind' => 'SPAN_KIND_UNSPECIFIED',
            'name' => $span->getOperationName(),
            'startTime' => $this->formatTimestamp($span->getStartTimestamp()),
            'endTime' => $this->formatTimestamp($span->getEndTimestamp()),
            'labels' => [
                'test' => 'true',
                'spanId' => (string) $context->getSpanId(),
                'traceId' => $context->getTraceId(),
            ],
        ];
        if ($span->getParentSpanId() !== null) {
            $traceSpan['parentSpanId'] = (string) $span->getParentSpanId();
            $traceSpan['labels']['parent'] = (string) $span->getParentSpanId();
        }

        $this->traces[$context->getTraceId()][] = $traceSpan;
    }

    public function upload()
    {
        if (empty($this->traces)) {
            return;
        }

        $traces = [];
        foreach ($this->traces as $traceId => $spans) {
            // https://cloud.google.com/trace/docs/reference/v1/rest/v1/projects.traces#Trace
            $traces[] = [
                'projectId' => $this->projectId,
                'traceId' => $traceId,
                'spans' => $spans,
            ];
        }

        // https://cloud.google.com/trace/docs/reference/v1/rest/v1/projects/patchTraces
        $data = json_encode([ 'traces' => $traces ]);

        // Send the request
        $process = $this->createRequestProcess($data);
        $process->run();
    }

    /**
     * @param string $json
     * @return Process
     */
    private function createRequestProcess(string $json) : Process
    {
        $arguments = [
            'curl',
            '--request',
            'PATCH',
            '--header', 'Content-Type: application/json',
            '--header', sprintf('Authorization: Bearer %s', $this->authHandler->getAccessToken()),
            '--data-raw',
            $json,
            '--retry',
            2,
            '--silent',
            $this->projectUrl,
        ];

        $script = implode(' ', array_map([ProcessUtils::class, 'escapeArgument'], $arguments));

        // Ensure we run the command in the background so it keeps alive after the php process has gone.
        if (in_array(PHP_OS, ['WINNT', 'WIN32', 'Windows'], true)) {
            $script  = 'start "" '. $script;
        } else {
            $script  = $script . '  > /dev/null 2>&1 &';
        }

        $process = new Process($script, null, [], null, 2);
        $process->disableOutput();

        return $process;
    }

    /**
     * @param float $timestamp
     * @return string
     */
    private function formatTimestamp(float $timestamp) : string
    {
        return \DateTime::createFromFormat('U.u', (string) $timestamp)->format('Y-m-d\TH:i:s.uP');
    }
}
