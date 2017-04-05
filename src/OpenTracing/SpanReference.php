<?php declare(strict_types=1);

namespace HelloFresh\OpenTracing;

final class SpanReference
{
    /**
     * See http://opentracing.io/spec/#causal-span-references for more information about CHILD_OF references
     */
    const CHILD_OF = 'child_of';

    /**
     * See http://opentracing.io/spec/#causal-span-references for more information about FOLLOWS_FROM references
     */
    const FOLLOWS_FROM = 'follows_from';

    /**
     * @var string
     */
    private $type;

    /**
     * @var SpanContextInterface
     */
    private $context;

    /**
     * @param string $type
     * @param SpanContextInterface $context
     */
    public function __construct(string $type, SpanContextInterface $context)
    {
        $this->type = $type;
        $this->context = $context;
    }

    /**
     * @return string
     */
    public function getType() : string
    {
        return $this->type;
    }

    /**
     * @return SpanContextInterface
     */
    public function getContext() : SpanContextInterface
    {
        return $this->context;
    }
}
