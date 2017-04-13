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
        $this->assertEquals($baggage, $context->getBaggageItems());
    }

    /**
     * @test
     */
    public function baggageChangesShouldNotBeReflectedInTheContext()
    {
        $baggage = ['test' => 0];

        $context = new SpanContext('test', 1, true, $baggage);
        $this->assertCount(1, $context->getBaggageItems());
        $this->assertEquals(0, $context->getBaggageItems()['test']);

        $baggage['test'] = 1;
        $this->assertCount(1, $context->getBaggageItems());
        $this->assertEquals(0, $context->getBaggageItems()['test']);

        $baggage = $context->getBaggageItems();
        $baggage['test'] = 2;
        $baggage['testing'] = true;
        $this->assertCount(1, $context->getBaggageItems());
        $this->assertEquals(0, $context->getBaggageItems()['test']);
    }
}
