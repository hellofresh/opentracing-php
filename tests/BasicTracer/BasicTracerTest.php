<?php declare(strict_types=1);

namespace Tests\HelloFresh\BasicTracer;

use HelloFresh\BasicTracer\BasicTracer;
use HelloFresh\BasicTracer\Exception\ExtractionException;
use HelloFresh\BasicTracer\Exception\InjectionException;
use HelloFresh\BasicTracer\Propagation\ExtractorInterface;
use HelloFresh\BasicTracer\Propagation\InjectorInterface;
use HelloFresh\BasicTracer\RecorderInterface;
use HelloFresh\BasicTracer\Span;
use HelloFresh\BasicTracer\SpanContext;
use HelloFresh\OpenTracing\SpanContextInterface;
use HelloFresh\OpenTracing\SpanReference;
use HelloFresh\OpenTracing\TracerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;

/**
 * @covers \HelloFresh\BasicTracer\BasicTracer
 * @uses \HelloFresh\BasicTracer\Span
 * @uses \HelloFresh\BasicTracer\SpanContext
 * @uses \HelloFresh\BasicTracer\NoopRecorder
 * @uses \HelloFresh\OpenTracing\SpanReference
 */
class BasicTracerTest extends TestCase
{
    /**
     * @test
     */
    public function aNewSpanCanBeStarted()
    {
        $tracer = $this->provideTracer();

        $beforeTime = microtime(true);
        $span = $tracer->startSpan(__METHOD__);
        $afterTime = microtime(true);

        $this->assertInstanceOf(Span::class, $span);
        /** @var Span $span */
        $this->assertGreaterThan($beforeTime, $span->getStartTimestamp());
        $this->assertLessThan($afterTime, $span->getStartTimestamp());
        $this->assertNull($span->getEndTimestamp());
    }

    /**
     * @test
     */
    public function aSpanCanBeStartedWithTagsAndStartTime()
    {
        $startTime = 1492093772.5368;
        $tags = ['foo' => 'bar', 'bar' => 'foo'];

        $span = $this->provideTracer()->startSpan(__METHOD__, [], $startTime, $tags);

        $this->assertInstanceOf(Span::class, $span);
        /** @var Span $span */
        $this->assertSame($startTime, $span->getStartTimestamp());
        $this->assertSame($tags, $span->getTags());
    }

    /**
     * @test
     */
    public function aChildSpanCanBeStarted()
    {
        $childContext = new SpanContext('traceId', 1331, true, ['b'=>'a','c'=>'d']);

        $span = $this->provideTracer()->startSpan(__METHOD__, [
            new SpanReference(
                'randomContext',
                $this->prophesize(SpanContext::class)->reveal()
            ),
            new SpanReference(
                SpanReference::CHILD_OF,
                $childContext
            ),
        ]);

        $context = $span->context();
        $this->assertInstanceOf(SpanContext::class, $context);
        /** @var SpanContext $context */
        $this->assertNotEquals($childContext->getSpanId(), $context->getSpanId());
        $this->assertSame($childContext->getTraceId(), $context->getTraceId());
        $this->assertSame($childContext->getBaggageItems(), $context->getBaggageItems());
        $this->assertSame($childContext->isSampled(), $context->isSampled());
    }

    /**
     * @test
     */
    public function sampledSpansAreRecorded()
    {
        $recorder = $this->prophesize(RecorderInterface::class);
        $tracer = new BasicTracer($recorder->reveal(), function () {
            return true;
        });

        $span = $tracer->startSpan(__METHOD__);
        $span->finish();

        $recorder->record(Argument::is($span))->shouldBeCalled();
    }

    /**
     * @test
     */
    public function unSampledSpansAreNotRecorded()
    {
        $recorder = $this->prophesize(RecorderInterface::class);

        $tracer = new BasicTracer($recorder->reveal(), function () {
            return false;
        });
        $span = $tracer->startSpan(__METHOD__);

        $span->finish();

        $recorder->record(Argument::any())->shouldNotBeCalled();
    }

    /**
     * @test
     */
    public function aContextCanBeInjected()
    {
        $context = $this->prophesize(SpanContext::class)->reveal();

        $carrier = new \stdClass();
        $expectedCarrier = new \stdClass();

        $injector = $this->prophesize(InjectorInterface::class);
        $injector
            ->inject(Argument::is($context), Argument::is($carrier))
            ->willReturn($expectedCarrier)
            ->shouldBeCalledTimes(1);

        $tracer = $this->provideTracer();
        $tracer->registerInjector(TracerInterface::FORMAT_TEXT_MAP, $injector->reveal());

        $resultingCarrier = $tracer->inject($context, TracerInterface::FORMAT_TEXT_MAP, $carrier);

        $this->assertSame($expectedCarrier, $resultingCarrier);
    }

    /**
     * @test
     */
    public function throwAnExceptionWhenAUnknownInjectionFormatIsProvided()
    {
        $tracer = $this->provideTracer();

        $context = $this->prophesize(SpanContextInterface::class);

        $this->expectException(InjectionException::class);
        $tracer->inject($context->reveal(), TracerInterface::FORMAT_TEXT_MAP, []);
    }

    /**
     * @test
     */
    public function aSpanContextCanBeExtracted()
    {
        $dataContainer = new \stdClass();
        $expectedContext = $this->prophesize(SpanContext::class)->reveal();

        $extractor = $this->prophesize(ExtractorInterface::class);
        $extractor->extract(Argument::is($dataContainer))->willReturn($expectedContext)->shouldBeCalledTimes(1);

        $tracer = $this->provideTracer();
        $tracer->registerExtractor(TracerInterface::FORMAT_TEXT_MAP, $extractor->reveal());

        $context = $tracer->extract(TracerInterface::FORMAT_TEXT_MAP, $dataContainer);

        $this->assertSame($expectedContext, $context);
    }

    /**
     * @test
     */
    public function throwAnExceptionWhenAUnknownExtractionFormatIsProvided()
    {
        $tracer = $this->provideTracer();

        $this->expectException(ExtractionException::class);
        $tracer->extract(TracerInterface::FORMAT_TEXT_MAP, []);
    }

    /**
     * @return RecorderInterface
     */
    private function mockRecorder() : RecorderInterface
    {
        return $this->prophesize(RecorderInterface::class)->reveal();
    }

    /**
     * @param RecorderInterface|null $recorder
     * @return BasicTracer
     */
    private function provideTracer(RecorderInterface $recorder = null) : BasicTracer
    {
        return new BasicTracer($recorder ?: $this->mockRecorder());
    }
}
