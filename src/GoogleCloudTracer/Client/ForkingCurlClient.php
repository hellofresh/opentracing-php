<?php declare(strict_types=1);

namespace HelloFresh\GoogleCloudTracer\Client;

use HelloFresh\GoogleCloudTracer\Auth\AuthProviderInterface;
use HelloFresh\GoogleCloudTracer\Exception\AccessTokenException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessUtils;

class ForkingCurlClient implements RecorderClientInterface
{
    /**
     * @var AuthProviderInterface
     */
    private $authProvider;

    /**
     * @var string CloudTrace Project ID
     */
    private $projectId;

    /**
     * @var string Google CloudTrace URL
     */
    private $projectUrl;

    /**
     * @var string CURL Output
     */
    private $logFile;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * GoogleTracesClient constructor.
     *
     * @param AuthProviderInterface $authProvider
     * @param string $projectId
     * @param string|null $curlOutputFile
     */
    public function __construct(
        AuthProviderInterface $authProvider,
        string $projectId,
        LoggerInterface $logger = null,
        string $curlOutputFile = null
    ) {
        $this->authProvider = $authProvider;
        $this->projectId = $projectId;
        $this->logger = $logger ?? new NullLogger();
        $this->logFile = $curlOutputFile;

        $this->projectUrl = sprintf(
            'https://cloudtrace.googleapis.com/v1/projects/%s/traces',
            urlencode($projectId)
        );
    }

    /**
     * @param string $traceJson
     *
     * @return bool
     */
    public function patchTraces(string $jsonData) : bool
    {
        try {
            $process = $this->createRequestProcess($jsonData);

            return $process->run() === 0;
        } catch (AccessTokenException $exception) {
            $this->logger->error('Authentication failure!', [
                'exception' => $exception,
            ]);

            return false;
        }
    }

    /**
     * @param string $json
     *
     * @return Process
     */
    private function createRequestProcess(string $json) : Process
    {
        $arguments = [
            'curl',
            '--request',
            'PATCH',
            '--header',
            'Content-Type: application/json',
            '--header',
            ProcessUtils::escapeArgument(
                sprintf('Authorization: Bearer %s', $this->authProvider->getAccessToken())
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
            $script = 'start "" ' . $script;
        } else {
            if ($this->logFile !== null) {
                $logTo = ProcessUtils::escapeArgument($this->logFile);
                $script = $script . '  >> ' . $logTo . ' 2>&1 &';
            } else {
                $script = $script . '  > /dev/null 2>&1 &';
            }
        }

        $process = new Process($script, null, [], null, 2);
        $process->disableOutput();

        return $process;
    }
}
