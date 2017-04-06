<?php declare(strict_types=1);

namespace HelloFresh\GoogleCloudTracer;

use HelloFresh\BasicTracer\RecorderInterface;
use HelloFresh\BasicTracer\Span;
use HelloFresh\BasicTracer\SpanContext;
use HelloFresh\OpenTracing\SpanInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

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
            'labels' => ['test' => 'true'],
        ];
        if ($span->getParentSpanId() !== null) {
            $traceSpan['parentSpanId'] = (string) $span->getParentSpanId();
        }

        // https://cloud.google.com/trace/docs/reference/v1/rest/v1/projects/patchTraces
        $data = json_encode([
            'traces' => [
                [
                    'projectId' => $this->projectId,
                    'traceId' => (string) $context->getTraceId(),
                    'spans' => [
                        $traceSpan,
                    ],
                ],
            ],
        ]);

        // Send the request
        $process = $this->createRequestProcess($data);
        $process->start();
    }

    /**
     * @param string $json
     * @return Process
     */
    private function createRequestProcess(string $json) : Process
    {
        return ProcessBuilder::create([
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
        ])
            ->inheritEnvironmentVariables(false)
            ->enableOutput()
            ->setTimeout(2)
            ->getProcess();
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