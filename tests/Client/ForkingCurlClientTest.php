<?php declare(strict_types=1);

namespace Tests\HelloFresh\Client;

use HelloFresh\GoogleCloudTracer\Auth\AuthProviderInterface;
use HelloFresh\GoogleCloudTracer\Client\ForkingCurlClient;
use HelloFresh\GoogleCloudTracer\Exception\AccessTokenException;
use HelloFresh\OpenTracing\SpanKind;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;

class ForkingCurlClientTest extends TestCase
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ForkingCurlClient
     */
    private $client;

    /**
     * @var AuthProviderInterface
     */
    private $auth;

    public function setUp()
    {
        $this->auth = $this->prophesize(AuthProviderInterface::class);
        $this->logger = $this->prophesize(LoggerInterface::class);
        $this->client = new ForkingCurlClient(
            $this->auth->reveal(),
            'project-id',
            $this->logger->reveal()
        );
    }

    /**
     * @test
     */
    public function logsAuthException()
    {
        $data = [
            'projectId' => 'project-id',
            'traceId' => 'abc123def456ab12',
            'spans' => [
                [
                    'spanId' => '123def456ab12abc',
                    'kind' => SpanKind::UNSPECIFIED,
                    'name' => 'span-operation-name',
                ],
            ],
        ];

        $this->auth->getAccessToken()->willThrow(AccessTokenException::class);
        $this->logger->error(Argument::type('string'), Argument::type('array'));

        $result = $this->client->patchTraces([$data]);

        $this->assertFalse($result);
    }
}
