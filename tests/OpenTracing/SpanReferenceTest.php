<?php declare(strict_types=1);

namespace Tests\HelloFresh\OpenTracing;

use HelloFresh\OpenTracing\SpanContextInterface;
use HelloFresh\OpenTracing\SpanReference;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HelloFresh\OpenTracing\SpanReference
 */
class SpanReferenceTest extends TestCase
{
    /**
     * @test
     */
    public function itShouldReturnConstructedValues()
    {
        $context = $this->prophesize(SpanContextInterface::class)->reveal();
        $type = SpanReference::FOLLOWS_FROM;

        $span = new SpanReference($type, $context);

        $this->assertSame($type, $span->getType(), 'Expected type not to change');
        $this->assertSame($context, $span->getContext(), 'Expected context not to change');
    }
}
