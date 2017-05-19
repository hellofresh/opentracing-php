<?php declare(strict_types=1);

namespace Tests\HelloFresh\GoogleCloudTracer\Formatter;

use HelloFresh\BasicTracer\NoopRecorder;
use HelloFresh\BasicTracer\Span;
use HelloFresh\BasicTracer\SpanContext;
use HelloFresh\GoogleCloudTracer\Formatter\GoogleCloudFormatter;
use HelloFresh\OpenTracing\SpanKind;
use PHPUnit\Framework\TestCase;

class GoogleCloudFormatterTest extends TestCase
{
    /**
     * @var GoogleCloudFormatter
     */
    private $formatter;

    public function setUp()
    {
        $this->formatter = new GoogleCloudFormatter();
    }

    /**
     * @test
     */
    public function checkTraceIsProperlyFormatted()
    {
        $recorder = new NoopRecorder();
        $traceId = '11aa43be4634f4da';
        $projectId = 'project-id';
        $spanContext = new SpanContext($traceId, 1, false);
        $span = new Span($recorder, microtime(true), 'trace-properly-formatted', $spanContext);
        $span->finish();

        $expectedData = [
            'traces' => [
                [
                    'projectId' => $projectId,
                    'traceId' => $traceId,
                    'spans' => [
                        [
                            'spanId' => '1',
                            'kind' => SpanKind::UNSPECIFIED,
                            'name' => 'trace-properly-formatted',
                        ],
                    ],
                ],
            ],
        ];

        $data = json_decode($this->formatter->formatTrace($projectId, $traceId, [$span]), true);
        $this->assertArrayHasKey('startTime', $data['traces'][0]['spans'][0]);
        $this->assertArrayHasKey('endTime', $data['traces'][0]['spans'][0]);
        unset($data['traces'][0]['spans'][0]['startTime']);
        unset($data['traces'][0]['spans'][0]['endTime']);
        $this->assertSame($expectedData, $data);
    }

    /**
     * @test
     */
    public function checkTraceIsProperlyFormattedWithSpanArray()
    {
        $span = [
            'spanId' => '1',
            'kind' => SpanKind::UNSPECIFIED,
            'name' => 'span-properly-formatted',
            'startTime' => '2017-05-18T11:57:10.096800+00:00',
            'endTime' => '2017-05-18T11:57:10.096800+00:00',
        ];
        $traceId = '11aa43be4634f4da';
        $projectId = 'project-id';

        $expectedData = [
            'traces' => [
                [
                    'projectId' => $projectId,
                    'traceId' => $traceId,
                    'spans' => [
                        $span,
                    ],
                ],
            ],
        ];

        $data = json_decode($this->formatter->formatTrace($projectId, $traceId, [$span]), true);
        $this->assertSame($expectedData, $data);
    }

    /**
     * @test
     */
    public function checkTraceIsNotCorrectlyFormattedWithWrongSpanArray()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageRegExp('/Missing required attributes/');

        $span = [
            'spanId' => '1',
            'kind' => SpanKind::UNSPECIFIED,
            'name' => 'span-properly-formatted',
        ];
        $traceId = '11aa43be4634f4da';
        $projectId = 'project-id';

        $this->formatter->formatTrace($projectId, $traceId, [$span]);
    }

    /**
     * @test
     */
    public function checkSpanIsCorrectlyFormatted()
    {
        $recorder = new NoopRecorder();
        $traceId = '11aa43be4634f4da';
        $spanContext = new SpanContext($traceId, 1, false);
        $span = new Span($recorder, microtime(true), 'span-properly-formatted', $spanContext);
        $span->finish();

        $expectedData = [
            'spanId' => '1',
            'kind' => SpanKind::UNSPECIFIED,
            'name' => 'span-properly-formatted',
        ];
        $data = $this->formatter->formatSpan($span);
        $this->assertArrayHasKey('startTime', $data);
        $this->assertArrayHasKey('endTime', $data);
        unset($data['startTime']);
        unset($data['endTime']);

        $this->assertSame($expectedData, $data);
    }
}
