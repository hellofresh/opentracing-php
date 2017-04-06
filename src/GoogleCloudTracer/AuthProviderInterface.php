<?php declare(strict_types=1);

namespace HelloFresh\GoogleCloudTracer;

interface AuthProviderInterface
{
    /**
     * @return string
     */
    public function getAccessToken() : string;
}
