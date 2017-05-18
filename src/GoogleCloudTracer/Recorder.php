<?php declare(strict_types=1);

namespace HelloFresh\GoogleCloudTracer;

use HelloFresh\BasicTracer\RecorderInterface;
use HelloFresh\BasicTracer\Span;
use HelloFresh\BasicTracer\SpanContext;
use HelloFresh\GoogleCloudTracer\Formatter\GoogleCloudFormatter;
use HelloFresh\GoogleCloudTracer\Formatter\TraceFormatterInterface;
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
     * @var TraceFormatterInterface
     */
    private $formatter;

    /**
     * @param AuthProviderInterface $authHandler
     * @param string $projectId
     * @param TraceFormatterInterface|null $formatter
     */
    public function __construct(
        AuthProviderInterface $authHandler,
        string $projectId,
        TraceFormatterInterface $formatter = null
    ) {
        if ($formatter === null) {
            $formatter = new GoogleCloudFormatter();
        }

        $this->authHandler = $authHandler;
        $this->projectId = $projectId;
        $this->formatter = $formatter;

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

        // Resolve the traceId for google cloud
        // Related to https://github.com/hellofresh/gcloud-opentracing/blob/master/recorder.go#L98
        $traceId = $context->getTraceId();
        if (strlen($traceId) === 36) {
            $traceId = str_replace('-', '', $traceId);
        }
        if (strlen($traceId) === 16) {
            $traceId = $traceId . $traceId;
        }

        $data = $this->formatter->formatTrace($this->projectId, $traceId, [$span]);

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
}
