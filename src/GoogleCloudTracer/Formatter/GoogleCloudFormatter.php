<?php declare(strict_types=1);

namespace HelloFresh\GoogleCloudTracer\Formatter;

use HelloFresh\BasicTracer\Span;
use HelloFresh\BasicTracer\SpanContext;
use HelloFresh\BasicTracer\Tags;
use HelloFresh\OpenTracing\SpanInterface;
use HelloFresh\OpenTracing\SpanKind;

class GoogleCloudFormatter implements TraceFormatterInterface
{
    /**
     * @inheritdoc
     */
    public function formatTrace(string $projectId, string $traceId, array $spans)
    {
        $spanArrays = array_map(function ($span) {
            return is_array($span) ? $this->assertSpanArray($span) : $this->formatSpan($span);
        }, $spans);

        return json_encode([
            'traces' => [
                [
                    'projectId' => $projectId,
                    'traceId' => $traceId,
                    'spans' => $spanArrays,
                ],
            ],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function formatSpan(SpanInterface $span)
    {
        if (!$span instanceof Span) {
            throw new \Exception('Instance of Span is necessary proper formatting');
        }

        $context = $span->context();
        if (!$context instanceof SpanContext) {
            throw new \Exception('Instance of SpanContext is necessary proper formatting');
        }

        // https://cloud.google.com/trace/docs/reference/v1/rest/v1/projects.traces#TraceSpan
        $traceSpan = [
            'spanId' => (string) $context->getSpanId(),
            'kind' => $this->getSpanKind($span),
            'name' => $span->getOperationName(),
            'startTime' => $this->formatTimestamp($span->getStartTimestamp()),
            'endTime' => $this->formatTimestamp($span->getEndTimestamp()),
        ];

        if ($span->getParentSpanId() !== null) {
            $traceSpan['parentSpanId'] = (string) $span->getParentSpanId();
        }

        $labels = $this->extractLabels($span);
        if (!empty($labels)) {
            $traceSpan['labels'] = $labels;
        }

        return $traceSpan;
    }

    /**
     * @param float $timestamp
     *
     * @return string
     */
    private function formatTimestamp(float $timestamp) : string
    {
        return \DateTime::createFromFormat('U.u', (string) $timestamp)->format('Y-m-d\TH:i:s.uP');
    }

    /**
     * @see https://github.com/opentracing/specification/blob/master/semantic_conventions.md#rpcs
     *
     * @param Span $span
     *
     * @return string
     */
    private function getSpanKind(Span $span) : string
    {
        $tags = $span->getTags();
        $kind = $tags[Tags::SPAN_KIND] ?? null;

        switch ($kind) {
            case 'server':
                return SpanKind::RPC_SERVER;
            case 'client':
                return SpanKind::RPC_CLIENT;
            default:
                return SpanKind::UNSPECIFIED;
        }
    }

    /**
     * Extract information from the span into gcloud trace labels
     *
     * @param Span $span
     *
     * @return array
     */
    private function extractLabels(Span $span) : array
    {
        $labels = [];

        $this->appendTags($labels, $span);
        $this->appendLogs($labels, $span);

        return $labels;
    }

    /**
     * Append opentracing tags into gcloud trace labels
     *
     * @param Span $span
     * @param array $labels
     */
    private function appendTags(array &$labels, Span $span)
    {
        // Rewrite opentracinglabels into those gcloud-native labels
        // https://github.com/GoogleCloudPlatform/google-cloud-go/blob/master/trace/trace.go#L178
        $labelMap = [
            Tags::PEER_HOSTNAME => 'trace.cloud.google.com/http/host',
            Tags::HTTP_METHOD => 'trace.cloud.google.com/http/method',
            Tags::HTTP_STATUS_CODE => 'trace.cloud.google.com/http/status_code',
            Tags::HTTP_URL => 'trace.cloud.google.com/http/url',
        ];

        foreach ($span->getTags() as $key => $value) {
            if (isset($labelMap[$key])) {
                $key = $labelMap[$key];
            }

            $labels[$key] = (string) $value;
        }
    }

    /**
     * Append opentracing logs into gcloud trace labels
     *
     * @param array $labels
     * @param Span $span
     */
    private function appendLogs(array &$labels, Span $span)
    {
        foreach ($span->getLogs() as $key => $values) {
            foreach ($values as $i => $info) {
                if (empty($info[1])) {
                    $value = $info[0];
                } else {
                    $value = sprintf('%s %d', $info[0], $info[1]);
                }

                $labels[sprintf('log_%s_%d', $key, $i)] = (string) $value;
            }
        }
    }

    /**
     * @param array $span
     *
     * @return array
     */
    private function assertSpanArray(array $span) : array
    {
        $requiredKeys = ['spanId', 'kind', 'name', 'startTime', 'endTime'];
        $diff = array_diff($requiredKeys, array_keys($span));
        if ($diff) {
            throw new \Exception(sprintf('Missing required attributes %s', implode(', ', $diff)));
        }

        return $span;
    }
}
