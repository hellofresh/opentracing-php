<?php declare(strict_types=1);

namespace HelloFresh\GoogleCloudTracer;

class Config
{
    /**
     * @var string
     */
    private $accessTokenCacheKey;
    /**
     * @var string
     */
    private $email;
    /**
     * @var string
     */
    private $privateKey;
    /**
     * @var string
     */
    private $projectId;

    public function __construct(
        string $email,
        string $privateKey,
        string $projectId,
        string $accessTokenCacheKey = 'google_cloud_tracer.access_token'
    ) {
        $this->accessTokenCacheKey = $accessTokenCacheKey;
        $this->email = $email;
        $this->privateKey = $privateKey;
        $this->projectId = $projectId;
    }

    /**
     * @return string
     */
    public function getAccessTokenCacheKey() : string
    {
        return $this->accessTokenCacheKey;
    }

    /**
     * @return string
     */
    public function getEmail() : string
    {
        return $this->email;
    }

    /**
     * @return string
     */
    public function getPrivateKey() : string
    {
        return $this->privateKey;
    }

    /**
     * @return string
     */
    public function getProjectId() : string
    {
        return $this->projectId;
    }
}
