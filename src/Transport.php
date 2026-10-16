<?php

namespace Watermark\Api;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Watermark\Api\Exceptions\ApiException;

/**
 * Sends calls to the API: the key, JSON in and out, and retries for what is
 * safe to retry (429 after Retry-After, 5xx, a dropped connection), with
 * an Idempotency-Key on every POST so a retried POST never runs twice.
 *
 * @internal
 */
class Transport
{
    public const VERSION = '0.2.0';

    private ClientInterface $http;

    /**
     * @param string               $apiKey
     * @param array<string, mixed> $options base_url, timeout, max_retries, http_client, sleep
     */
    public function __construct(private string $apiKey, private array $options = [])
    {
        $this->options += [
            'base_url'    => 'https://api.watermark.ws',
            'timeout'     => 30,
            'max_retries' => 2,
            'sleep'       => fn (float $seconds) => usleep((int) ($seconds * 1_000_000)),
        ];

        $this->http = $this->options['http_client'] ?? new Guzzle();
    }

    /**
     * @param string                    $method
     * @param string                    $path
     * @param array<string, mixed>|null $body
     * @param array<string, mixed>      $query
     * @param string|null               $idempotencyKey
     *
     * @return array<string, mixed>
     * @throws ApiException
     */
    public function request(string $method, string $path, ?array $body = null, array $query = [], ?string $idempotencyKey = null):array
    {
        $headers = [
            'Authorization' => "Bearer {$this->apiKey}",
            'Accept'        => 'application/json',
            'User-Agent'    => 'watermark-php/' . self::VERSION . ' PHP/' . PHP_VERSION,
        ];

        if ($method === 'POST') {
            $headers['Idempotency-Key'] = $idempotencyKey ?? bin2hex(random_bytes(16));
        }

        $options = [
            RequestOptions::HEADERS     => $headers,
            RequestOptions::TIMEOUT     => $this->options['timeout'],
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::QUERY       => array_filter($query, fn ($value) => $value !== null),
        ];

        if ($body !== null) {
            $options[RequestOptions::JSON] = $body;
        }

        $url = rtrim($this->options['base_url'], '/') . $path;

        for ($attempt = 0; ; $attempt++) {
            try {
                $response = $this->http->request($method, $url, $options);
            } catch (ConnectException $e) {
                if ($attempt >= $this->options['max_retries']) {
                    throw $e;
                }

                ($this->options['sleep'])($this->backoff($attempt));

                continue;
            }

            $status = $response->getStatusCode();

            if (($status === 429 || $status >= 500) && $attempt < $this->options['max_retries']) {
                ($this->options['sleep'])($this->retryAfter($response) ?? $this->backoff($attempt));

                continue;
            }

            $decoded = json_decode((string) $response->getBody(), true) ?? [];

            if ($status >= 400) {
                throw ApiException::fromResponse($status, $decoded);
            }

            return $decoded;
        }
    }

    /**
     * @param int $attempt
     *
     * @return float Seconds: 0.5, 1, 2, ... with jitter.
     */
    private function backoff(int $attempt):float
    {
        return 0.5 * (2 ** $attempt) * (0.75 + mt_rand() / mt_getrandmax() / 2);
    }

    /**
     * @param ResponseInterface $response
     *
     * @return float|null
     */
    private function retryAfter(ResponseInterface $response):?float
    {
        $header = $response->getHeaderLine('Retry-After');

        return is_numeric($header) ? min(60.0, (float) $header) : null;
    }
}
