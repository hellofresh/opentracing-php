<?php declare(strict_types=1);

namespace HelloFresh\OpenTracing;

class SpanKind
{
    const RPC_SERVER = 'RPC_SERVER';
    const RPC_CLIENT = 'RPC_CLIENT';
    const UNSPECIFIED = 'SPAN_KIND_UNSPECIFIED';
}
