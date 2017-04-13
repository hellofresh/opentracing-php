<?php declare(strict_types=1);

namespace Tests\HelloFresh\BasicTracer\Propagation;

use HelloFresh\BasicTracer\Exception\ExtractionException;
use HelloFresh\BasicTracer\Propagation\TextMapPropagator;
use HelloFresh\BasicTracer\SpanContext;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HelloFresh\BasicTracer\Propagation\TextMapPropagator
 * @uses \HelloFresh\BasicTracer\SpanContext
 */
class TextMapPropagatorTest extends TestCase
{
    /**
     * @test
     */
    public function itExtractsDataFromArrays()
    {
        $extractor = new TextMapPropagator();

        $data = [
            'ot-tracer-traceid' => 'WeRule',
            'ot-tracer-spanid' => '1234',
            'ot-tracer-sampled' => '1',
        ];
        $expectedContext = new SpanContext('WeRule', 1234, true, []);

        $context = $extractor->extract($data);
        $this->assertEquals($expectedContext, $context);

        $data = new \ArrayObject([
            'ot-tracer-traceid' => 'e2ba47ec8c1b',
            'ot-tracer-spanid' => '987',
            'ot-tracer-sampled' => 'false',
            'ot-baggage-user' => 'test',
            'ot-baggage-role' => 'unit',
        ]);
        $expectedContext = new SpanContext('e2ba47ec8c1b', 987, false, ['user'=>'test', 'role'=>'unit']);

        $context = $extractor->extract($data);
        $this->assertEquals($expectedContext, $context);
    }

    /**
     * @test
     * @dataProvider provideMissingExtractionDataArrays
     *
     * @param array $data
     */
    public function failExtractionWhenNotEnoughDataIsAvailable(array $data)
    {
        $extractor = new TextMapPropagator();

        $this->expectException(ExtractionException::class);
        $extractor->extract($data);
    }

    /**
     * @test
     * @dataProvider provideInvalidExtractionCarriers
     *
     * @param mixed$data
     */
    public function failExtractionWhenTheCarrierIsNotSupported($data)
    {
        $extractor = new TextMapPropagator();

        $this->expectException(ExtractionException::class);
        $this->expectExceptionMessage('Unsupported carrier');
        $extractor->extract($data);
    }

    public function provideMissingExtractionDataArrays()
    {
        return [
            [ [] ],
            [
                [
                    'ot-tracer-spanid' => '1234',
                    'ot-tracer-sampled' => '1',
                ],
            ],
            [
                [
                    'ot-tracer-traceid' => 'WeRule',
                    'ot-tracer-sampled' => '1',
                ],
            ],
            [
                [
                    'ot-tracer-traceid' => 'WeRule',
                    'ot-tracer-spanid' => '1234',
                ],
            ],
        ];
    }

    public function provideInvalidExtractionCarriers()
    {
        return [
            [ null ],
            [ new \stdClass() ],
            [ 1 ],
            [ 'string' ],
        ];
    }
}
