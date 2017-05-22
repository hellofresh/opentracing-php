<?php declare(strict_types=1);

namespace Tests\HelloFresh\GoogleCloudTracer;

use HelloFresh\BasicTracer\Span;
use HelloFresh\BasicTracer\SpanContext;
use HelloFresh\GoogleCloudTracer\Client\RecorderClientInterface;
use HelloFresh\GoogleCloudTracer\Formatter\GoogleCloudFormatter;
use HelloFresh\GoogleCloudTracer\Recorder;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;

class RecorderTest extends TestCase
{
    /**
     * @var Recorder
     */
    private $recorder;

    /**
     * @var GoogleCloudFormatter|ObjectProphecy
     */
    private $formatter;

    /**
     * @var RecorderClientInterface|ObjectProphecy
     */
    private $client;

    /**
     * @var string
     */
    private $projectId = 'project-id';

    public function setUp()
    {
        $this->client = $this->prophesize(RecorderClientInterface::class);
        $this->formatter = $this->prophesize(GoogleCloudFormatter::class);
        $this->recorder = new Recorder(
            $this->client->reveal(),
            $this->projectId,
            $this->formatter->reveal()
        );
    }

    /**
     * @test
     */
    public function spanMustBeSampledToBePublished()
    {
        $context = new SpanContext('c1072bb1173c53d4c1072bb1173c53d4', 1, false);
        $span = new Span($this->recorder, microtime(true), 'recorder-test-span', $context);

        $this->client->patchTraces(Argument::any())->shouldNotBeCalled();
        $span->finish();
    }

    /**
     * @test
     */
    public function spanCanBePublishedIfSampled()
    {
        $context = new SpanContext('c1072bb1173c53d4c1072bb1173c53d4', 1, true);
        $span = new Span($this->recorder, microtime(true), 'recorder-test-span', $context);

        $this->formatter->formatTrace($this->projectId, $context->getTraceId(), [$span])
            ->shouldBeCalled()
            ->willReturn(['traces' => ['trace']]);

        $this->client->patchTraces(['traces' => ['trace']])
            ->shouldBeCalled()
            ->willReturn('access-token');

        $span->finish();
    }
}
