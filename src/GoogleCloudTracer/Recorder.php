<?php declare(strict_types=1);

namespace HelloFresh\GoogleCloudTracer;

use HelloFresh\BasicTracer\RecorderInterface;
use HelloFresh\BasicTracer\Span;
use HelloFresh\BasicTracer\SpanContext;
use HelloFresh\BasicTracer\Tags;
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
     * @var string
     */
    private $logFile;

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

        // https://cloud.google.com/trace/docs/reference/v1/rest/v1/projects.traces#TraceSpan
        $traceSpan = [
            'spanId' => (string) $context->getSpanId(),
            'kind' => $this->getSpanKind($span),
            'name' => $span->getOperationName(),
            'startTime' => $this->formatTimestamp($span->getStartTimestamp()),
            'endTime' => $this->formatTimestamp($span->getEndTimestamp()),
        ];
        if ($span->getParentSpanId() !== null) {
            $traceSpan['parentSpanId'] = (string) $span->getParentSpanId();
        }
        $labels = $this->extractLabels($span);
        if (!empty($labels)) {
            $traceSpan['labels'] = $labels;
        }

        // Resolve the traceId for google cloud
        // Related to https://github.com/hellofresh/gcloud-opentracing/blob/master/recorder.go#L98
        $traceId = $context->getTraceId();
        if (strlen($traceId) === 36) {
            $traceId = str_replace('-', '', $traceId);
        }
        if (strlen($traceId) === 16) {
            $traceId = $traceId . $traceId;
        }

        // https://cloud.google.com/trace/docs/reference/v1/rest/v1/projects/patchTraces
        $data = json_encode([ 'traces' => [
            [
                'projectId' => $this->projectId,
                'traceId' => $traceId,
                'spans' => $traceSpan,
            ],
        ] ]);

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
            '--header', ProcessUtils::escapeArgument(
                sprintf('Authorization: Bearer %s', $this->authHandler->getAccessToken())
            ),
            '--retry',
            2,
            '--silent',
            '--data',
            ProcessUtils::escapeArgument($json),
            ProcessUtils::escapeArgument($this->projectUrl),
        ];

        $script = implode(' ', $arguments);

        // Ensure we run the command in the background so it keeps alive after the php process has gone.
        if (in_array(PHP_OS, ['WINNT', 'WIN32', 'Windows'], true)) {
            $script  = 'start "" '. $script;
        } else {
            if ($this->logFile !== null) {
                $logTo = ProcessUtils::escapeArgument($this->logFile);
                $script  = $script . '  >> '.  $logTo .' 2>&1 &';
            } else {
                $script  = $script . '  > /dev/null 2>&1 &';
            }
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

    /**
     * @see https://github.com/opentracing/specification/blob/master/semantic_conventions.md#rpcs
     *
     * @param Span $span
     * @return string
     */
    private function getSpanKind(Span $span) : string
    {
        $tags = $span->getTags();
        $kind = $tags[Tags::SPAN_KIND] ?? null;

        switch ($kind) {
            case 'server':
                return 'RPC_SERVER';
            case 'client':
                return 'RPC_CLIENT';
            default:
                return 'SPAN_KIND_UNSPECIFIED';
        }
    }

    /**
     * Extract information from the span into gcloud trace labels
     *
     * @param Span $span
     * @return array
     */
    private function extractLabels(Span $span) : array
    {
        $labels = [];

        $this->appendTags($labels, $span);
        $this->appendLogs($labels, $span);

        return $labels;
    }

    /**
     * Append opentracing tags into gcloud trace labels
     *
     * @param Span $span
     * @param array $labels
     */
    private function appendTags(array &$labels, Span $span)
    {
        // Rewrite opentracinglabels into those gcloud-native labels
        // https://github.com/GoogleCloudPlatform/google-cloud-go/blob/master/trace/trace.go#L178
        $labelMap = [
            Tags::PEER_HOSTNAME => 'trace.cloud.google.com/http/host',
            Tags::HTTP_METHOD => 'trace.cloud.google.com/http/method',
            Tags::HTTP_STATUS_CODE => 'trace.cloud.google.com/http/status_code',
            Tags::HTTP_URL => 'trace.cloud.google.com/http/url',
        ];

        foreach ($span->getTags() as $key => $value) {
            if (isset($labelMap[$key])) {
                $key = $labelMap[$key];
            }

            $labels[$key] = (string) $value;
        }
    }

    /**
     * Append opentracing logs into gcloud trace labels
     *
     * @param array $labels
     * @param Span $span
     */
    private function appendLogs(array &$labels, Span $span)
    {
        foreach ($span->getLogs() as $key => $values) {
            foreach ($values as $i => $info) {
                if (empty($info[1])) {
                    $value = $info[0];
                } else {
                    $value = sprintf('%s %d', $info[0], $info[1]);
                }

                $labels[sprintf('log_%s_%d', $key, $i)] = (string) $value;
            }
        }
    }
}
