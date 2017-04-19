<?php declare(strict_types=1);

namespace Tests\HelloFresh\BasicTracer;

use HelloFresh\BasicTracer\Exception\SpanStateException;
use HelloFresh\BasicTracer\RecorderInterface;
use HelloFresh\BasicTracer\Span;
use HelloFresh\BasicTracer\SpanContext;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HelloFresh\BasicTracer\Span
 * @uses \HelloFresh\BasicTracer\SpanContext
 */
class SpanTest extends TestCase
{
    /**
     * @test
     */
    public function itShouldReturnConstructedValues()
    {
        $recorder = $this->mockRecorder();
        $context = $this->mockContext();
        $start = microtime(true);

        $span = new Span($recorder, $start, __METHOD__, $context, 123);

        $this->assertSame($start, $span->getStartTimestamp());
        $this->assertSame(__METHOD__, $span->getOperationName());
        $this->assertSame($context, $span->context());
        $this->assertSame(123, $span->getParentSpanId());
        $this->assertNull($span->getEndTimestamp());
        $this->assertEmpty($span->getTags());
        $this->assertEmpty($span->getLogs());
    }

    /**
     * @test
     */
    public function itShouldBeRecorderWhenFinished()
    {
        $recorder = $this->prophesize(RecorderInterface::class);

        $span = new Span($recorder->reveal(), microtime(true), __METHOD__, $this->mockContext());
        $span->finish();

        $recorder->record($span)->shouldBeCalledTimes(1);
        $this->assertInternalType('float', $span->getEndTimestamp());

        // Ensure we don't finish twice
        $span->finish();
        $recorder->record($span)->shouldBeCalledTimes(1);
    }

    /**
     * @test
     */
    public function overrideOperationName()
    {
        $span = $this->provideSpan();

        $span->setOperationName('test');
        $this->assertSame('test', $span->getOperationName());

        $span->setOperationName(__METHOD__);
        $this->assertSame(__METHOD__, $span->getOperationName());
    }

    /**
     * @test
     */
    public function baggageChangesShouldBeReflected()
    {
        $baseContext = new SpanContext('6c22fc48e5e9457da7b7ffed7932d043', 0, false, ['test' => 1, 'try' => 0]);

        $span  = new Span(
            $this->mockRecorder(),
            microtime(true),
            __METHOD__,
            $baseContext
        );

        $this->assertSame('1', $span->getBaggageItem('test'));
        $this->assertSame('0', $span->getBaggageItem('try'));
        $this->assertNull($span->getBaggageItem('new'));
        $this->assertSame($baseContext, $span->context());

        $this->assertSame($span, $span->setBaggageItem('new', 'yes'));
        $this->assertSame('yes', $span->getBaggageItem('new'));

        $context = $span->context();
        $this->assertNotSame($baseContext, $context);
        $this->assertSame(
            ['test' => '1', 'try' => '0', 'new' => 'yes'],
            $context->getBaggageItems()
        );
        $this->assertSame('6c22fc48e5e9457da7b7ffed7932d043', $context->getTraceId());
        $this->assertSame(0, $context->getSpanId());
        $this->assertFalse($context->isSampled());
    }

    /**
     * @test
     */
    public function logsAreAdded()
    {
        $span = $this->provideSpan();
        $this->assertEmpty($span->getLogs());

        $time1 = microtime(true);
        $time2 = microtime(true) + 1;

        $expectedLogs = [
            'test' => [
                ['abc', $time1],
                ['def', $time2],
            ],
            'foo' => [ ['d', $time2] ],
            'yes' => [ ['dear', null] ],
        ];

        $span->log('test', 'abc', $time1);
        $this->assertCount(1, $span->getLogs());

        $span->logs(['test' => 'def', 'foo' => 'd'], $time2);
        $this->assertCount(2, $span->getLogs());

        $span->logs(['yes' => 'dear']);
        $this->assertCount(3, $span->getLogs());

        $this->assertSame($expectedLogs, $span->getLogs());
    }

    /**
     * @test
     */
    public function tagsAreAdded()
    {
        $span = $this->provideSpan();
        $this->assertEmpty($span->getTags());

        $span->setTag('test', 'abc');
        $this->assertSame(['test' => 'abc'], $span->getTags());

        $span->setTag('test', 'abcd');
        $this->assertSame(['test' => 'abcd'], $span->getTags());

        $span->setTag('food', 'bar');
        $this->assertSame(['test' => 'abcd', 'food' => 'bar'], $span->getTags());
    }

    /**
     * @test
     */
    public function itShouldBeImmutableWhenFinishedSetOperationName()
    {
        $span = $this->provideFinishedSpan();

        $this->expectException(SpanStateException::class);
        $span->setOperationName('test');
    }

    /**
     * @test
     */
    public function itShouldBeImmutableWhenFinishedSetBaggage()
    {
        $span = $this->provideFinishedSpan();

        $this->expectException(SpanStateException::class);
        $span->setBaggageItem('test', '1');
    }

    /**
     * @test
     */
    public function itShouldBeImmutableWhenFinishedSetTag()
    {
        $span = $this->provideFinishedSpan();

        $this->expectException(SpanStateException::class);
        $span->setTag('test', 1);
    }

    /**
     * @test
     */
    public function itShouldBeImmutableWhenFinishedLog()
    {
        $span = $this->provideFinishedSpan();

        $this->expectException(SpanStateException::class);
        $span->log('test', 1);
    }

    /**
     * @return Span
     */
    private function provideSpan() : Span
    {
        return new Span(
            $this->mockRecorder(),
            microtime(true),
            __METHOD__,
            $this->mockContext()
        );
    }

    /**
     * @return Span
     */
    private function provideFinishedSpan() : Span
    {
        $span = $this->provideSpan();
        $span->finish();

        return $span;
    }

    /**
     * @return SpanContext
     */
    private function mockContext() : SpanContext
    {
        $context = $this->prophesize(SpanContext::class);
        $context->getBaggageItems()->willReturn([]);

        return $context->reveal();
    }

    /**
     * @return RecorderInterface
     */
    private function mockRecorder() : RecorderInterface
    {
        return $this->prophesize(RecorderInterface::class)->reveal();
    }
}
