<?php declare(strict_types=1);

namespace HelloFresh\GoogleCloudTracer;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\Cache;
use HelloFresh\BasicTracer\RecorderInterface;
use HelloFresh\BasicTracer\Span;
use HelloFresh\BasicTracer\SpanContext;
use HelloFresh\OpenTracing\SpanInterface;
use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Http\Promise\FulfilledPromise;
use Http\Promise\Promise;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Psr\Http\Message\ResponseInterface;

/**
 * @link https://cloud.google.com/trace/docs/reference/v1/rest/v1/projects/patchTraces
 * @link https://github.com/google/google-api-php-client-services/tree/master/src/Google/Service/CloudTrace
 */
class Recorder implements RecorderInterface
{
    /**
     * @var Config
     */
    private $config;
    /**
     * @var HttpAsyncClient
     */
    private $httpClient;
    /**
     * @var RequestFactory
     */
    private $requestFactory;
    /**
     * @var UriFactory
     */
    private $uriFactory;
    /**
     * @var Cache
     */
    private $cache;
    /**
     * @see https://cloud.google.com/trace/docs/reference/v1/rest/v1/projects.traces#TraceSpan
     *
     * @var array
     */
    private $traces;
    /**
     * @var Promise|null
     */
    private $accessTokenRequest;

    public function __construct(
        Config $config,
        HttpAsyncClient $client,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        Cache $cache = null
    ) {
        // TODO use external process
        $this->config = $config;

        $this->httpClient = $client;
        $this->requestFactory = $requestFactory;
        $this->uriFactory = $uriFactory;

        $this->cache = $cache ?? new ArrayCache();
    }

    /**
     * @param Span $span
     */
    public function record(SpanInterface $span)
    {
        /** @var SpanContext $context */
        $context = $span->context();

        // https://cloud.google.com/trace/docs/reference/v1/rest/v1/projects.traces#TraceSpan
        $traceSpan = [
            'kind' => 'SPAN_KIND_UNSPECIFIED',
            'spanId' => $context->getSpanId(),
            'parentSpanId' => $span->getParentSpanId(),
            'name' => $span->getOperationName(),
            'startTime' => date('Y-m-d\TH:i:s.uP', $span->getStartTimestamp()),
            'endTime' => date('Y-m-d\TH:i:s.uP', $span->getEndTimestamp()),
        ];
        // TODO: labels
        $this->traces[$context->getTraceId()][$context->getSpanId()] = $traceSpan;

        // Request the access token and then send all traces
        $this->requestToken()
            ->then(function (string $accessToken) {
                var_dump(__LINE__);
                $this->sendTraces($accessToken);
            });
    }

    /**
     * @see https://cloud.google.com/trace/docs/reference/v1/rest/v1/projects/patchTraces
     *
     * @param string $accessToken
     */
    private function sendTraces(string $accessToken)
    {
        $traces = [];
        foreach ($this->traces as $traceId => $spans) {
            $traces[] = [
                'projectId' => $this->config->getProjectId(),
                'traceId' => $traceId,
                'spans' => array_values($spans),
            ];
        }

        json_encode([ 'traces' => $traces ]);
    }

    protected function requestToken() : Promise
    {
        // Get token from the cache
        $accessToken = $this->cache->fetch($this->config->getAccessTokenCacheKey());
//        $accessToken = 'ya29.ElwkBN21QKK-aB9GgxRNDgfDRCmVsSDJU4ETJZRAeuELOjOCLWZMLQEHoL39_XTBq119plbkSOeeKhkM9dHX2nx5Hg14yQF6pD4MU_M1JzikZNPA7g0JxB-CYH4f2w';
        if ($accessToken !== false) {
            return new FulfilledPromise($accessToken);
        }

        if ($this->accessTokenRequest !== null) {
            var_dump($this->accessTokenRequest->getState());

            return $this->accessTokenRequest;
        }

        // Get Access token
        // https://developers.google.com/identity/protocols/OAuth2ServiceAccount
        $tokenExpiration = time() + 3600;
        $token = (new Builder())
            ->set('scope', implode(' ', [
                'https://www.googleapis.com/auth/trace.append',
                'https://www.googleapis.com/auth/cloud-platform',
            ]))
            ->setIssuedAt(time())
            ->setId('<secret>')
            ->setIssuer($this->config->getEmail())
            ->setExpiration($tokenExpiration)
            ->setAudience('https://www.googleapis.com/oauth2/v4/token')
            ->sign(new Sha256(), $this->config->getPrivateKey())
            ->getToken()
        ;

        $request = $this->requestFactory->createRequest('POST', 'https://www.googleapis.com/oauth2/v4/token',
            [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
            http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => (string) $token,
            ])
        );

        $promise = $this->httpClient->sendAsyncRequest($request);
        $this->accessTokenRequest = $promise;

        $promise->then(
            function (ResponseInterface $response) use ($tokenExpiration) {
                $data = json_decode($response->getBody()->getContents());
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \InvalidArgumentException(
                        sprintf('Access Token response json_decode error %s', json_last_error_msg())
                    );
                }

                if (empty($data['access_token'])) {
                    throw new \InvalidArgumentException('Access Token response provides no access token');
                }
                $accessToken = $data['access_token'];

                $this->cache->save($this->config->getAccessTokenCacheKey(), $accessToken, time() - $tokenExpiration);

                return $accessToken;
            },
            function ($reason) {
                if ($reason instanceof \Throwable) {
                    throw $reason;
                }
            }
        );

        return $promise;
    }
}
