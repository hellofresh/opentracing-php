<?php declare(strict_types=1);

namespace HelloFresh\GoogleCloudTracer;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\Cache;
use HelloFresh\GoogleCloudTracer\Exception\AccessTokenException;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

class AuthProvider implements AuthProviderInterface
{
    /**
     * @var string
     */
    private $email;
    /**
     * @var string
     */
    private $privateId;
    /**
     * @var string
     */
    private $privateKey;
    /**
     * @var Cache
     */
    private $cache;
    /**
     * @var string
     */
    private $cacheKey;

    public function __construct(
        string $email,
        string $privateId,
        string $privateKey,
        Cache $cache = null,
        string $cacheKey = 'google_cloud_tracer.access_token'
    ) {
        $this->email = $email;
        $this->privateId = $privateId;
        $this->privateKey = $privateKey;
        $this->cache = $cache ?: new ArrayCache();
        $this->cacheKey = $cacheKey;
    }

    /**
     * @return string
     */
    public function getAccessToken() : string
    {
        $accessToken = $this->cache->fetch($this->cacheKey);
        if ($accessToken !== false) {
            return (string) $accessToken;
        }

        $tokenExpiration = time() + 3600;
        $token = $this->generateJwt($tokenExpiration);

        $data = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => (string) $token,
        ]);

        $process = $this->createRequestProcess($data);
        $process->mustRun();

        $accessToken = $this->getTokenFromProcessOutput($process->getOutput());
        $this->cache->save($this->cacheKey, $accessToken, $tokenExpiration - time());

        return $accessToken;
    }

    /**
     * @param int $tokenExpiration
     * @return \Lcobucci\JWT\Token
     */
    private function generateJwt(int $tokenExpiration)
    {
        return (new Builder())
            ->set('scope', implode(' ', [
                'https://www.googleapis.com/auth/trace.append',
                'https://www.googleapis.com/auth/cloud-platform',
            ]))
            ->setIssuedAt(time())
            ->setId($this->privateId)
            ->setIssuer($this->email)
            ->setExpiration($tokenExpiration)
            ->setAudience('https://www.googleapis.com/oauth2/v4/token')
            ->sign(new Sha256(), $this->privateKey)
            ->getToken()
        ;
    }

    /**
     * @param string $postData
     * @return Process
     */
    private function createRequestProcess(string $postData) : Process
    {
        return ProcessBuilder::create([
            'curl',
            '--request',
            'POST',
            '--data',
            $postData,
            '--retry',
            2,
            '--silent',
            'https://www.googleapis.com/oauth2/v4/token',
        ])
            ->inheritEnvironmentVariables(false)
            ->enableOutput()
            ->setTimeout(2)
            ->getProcess();
    }

    /**
     * @param string $output
     * @return string
     */
    private function getTokenFromProcessOutput(string $output) : string
    {
        $data = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new AccessTokenException(
                sprintf('Response json_decode error %s', json_last_error_msg())
            );
        }

        if (empty($data['access_token'])) {
            throw new AccessTokenException('Response contains no \'access_token\'');
        }

        return $data['access_token'];
    }
}
