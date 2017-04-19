<?php declare(strict_types=1);

namespace Tests\HelloFresh\BasicTracer;

use HelloFresh\BasicTracer\SpanContext;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HelloFresh\BasicTracer\SpanContext
 */
class SpanContextTest extends TestCase
{
    /**
     * @test
     */
    public function itShouldReturnConstructedValues()
    {
        $baggage = ['this' => 'is', 'baggage' => 'yo'];

        $context = new SpanContext('test', 1, true, $baggage);

        $this->assertSame('test', $context->getTraceId());
        $this->assertSame(1, $context->getSpanId());
        $this->assertTrue($context->isSampled());
        $this->assertSame($baggage, $context->getBaggageItems());
    }

    /**
     * @test
     */
    public function itWillCastBaggageValuesToString()
    {
        $expectedBaggage = ['this' => '1', 'baggage' => '123'];

        $context = new SpanContext('test', 1, true, ['this' => true, 'baggage' => 123]);

        $this->assertSame(
            $expectedBaggage,
            $context->getBaggageItems()
        );
    }

    /**
     * @test
     */
    public function baggageChangesShouldNotBeReflectedInTheContext()
    {
        $baggage = ['test' => '0'];

        $context = new SpanContext('test', 1, true, $baggage);
        $this->assertCount(1, $context->getBaggageItems());
        $this->assertSame('0', $context->getBaggageItems()['test']);

        $baggage['test'] = 1;
        $this->assertCount(1, $context->getBaggageItems());
        $this->assertSame('0', $context->getBaggageItems()['test']);

        $baggage = $context->getBaggageItems();
        $baggage['test'] = 2;
        $baggage['testing'] = true;
        $this->assertCount(1, $context->getBaggageItems());
        $this->assertSame('0', $context->getBaggageItems()['test']);
    }
}
