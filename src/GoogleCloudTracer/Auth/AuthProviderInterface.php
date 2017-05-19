<?php declare(strict_types=1);

namespace HelloFresh\GoogleCloudTracer\Auth;

interface AuthProviderInterface
{
    /**
     * @return string
     */
    public function getAccessToken() : string;
}
