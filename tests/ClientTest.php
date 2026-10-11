<?php

namespace Watermark\Api\Tests;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Watermark\Api\Client;
use Watermark\Api\Exceptions\InvalidRequestException;
use Watermark\Api\Exceptions\PaymentRequiredException;
use Watermark\Api\Exceptions\TimeoutException;

class ClientTest extends TestCase
{
    /**
     * @var array<int, array{request: \Psr\Http\Message\RequestInterface}>
     */
    private array $history = [];

    /**
     * @var array<int, float>
     */
    private array $slept = [];

    public function test_processing_posts_the_operations_with_the_key_and_an_idempotency_key():void
    {
        $client = $this->client([new Response(202, [], json_encode(['id' => 12, 'status' => 'queued']))]);

        $request = $client->images->process('https://example.com/a.jpg', [['type' => 'grayscale']], [
            'output'          => ['format' => 'webp'],
            'customer'        => 'cus_1',
            'idempotency_key' => 'order-7',
        ]);

        $this->assertSame(['id' => 12, 'status' => 'queued'], $request);

        $sent = $this->history[0]['request'];

        $this->assertSame('POST', $sent->getMethod());
        $this->assertSame('https://api.test/v1/images/process', (string) $sent->getUri());
        $this->assertSame('Bearer wm_test_key', $sent->getHeaderLine('Authorization'));
        $this->assertSame('order-7', $sent->getHeaderLine('Idempotency-Key'));
        $this->assertSame([
            'source'     => 'https://example.com/a.jpg',
            'operations' => [['type' => 'grayscale']],
            'output'     => ['format' => 'webp'],
            'customer'   => 'cus_1',
        ], json_decode((string) $sent->getBody(), true));
    }

    public function test_every_post_gets_an_idempotency_key_so_a_retry_runs_once():void
    {
        $client = $this->client([new Response(503), new Response(202, [], '{"id":1}')]);

        $client->documents->snapshot('https://example.com/a.pdf', 2);

        $this->assertCount(2, $this->history);
        $first = $this->history[0]['request']->getHeaderLine('Idempotency-Key');

        $this->assertNotSame('', $first);
        $this->assertSame($first, $this->history[1]['request']->getHeaderLine('Idempotency-Key'));
        $this->assertSame(['source' => 'https://example.com/a.pdf', 'page' => 2], json_decode((string) $this->history[1]['request']->getBody(), true));
    }

    public function test_a_rate_limit_waits_for_retry_after():void
    {
        $client = $this->client([new Response(429, ['Retry-After' => '3']), new Response(200, [], '{"object":"account"}')]);

        $this->assertSame(['object' => 'account'], $client->account->get());
        $this->assertSame([3.0], $this->slept);
    }

    public function test_errors_become_typed_exceptions():void
    {
        $client = $this->client([
            new Response(402, [], json_encode(['error' => ['type' => 'payment_required', 'code' => 'insufficient_credits', 'message' => 'No credits left.', 'request_id' => '55']])),
            new Response(422, [], json_encode(['error' => ['type' => 'invalid_request', 'code' => 'validation_failed', 'message' => 'Bad width.', 'param' => 'operations.0.width', 'errors' => ['operations.0.width' => ['Bad width.']]]])),
        ]);

        try {
            $client->images->read('https://example.com/a.jpg');
            $this->fail('No exception.');
        } catch (PaymentRequiredException $e) {
            $this->assertSame(402, $e->getStatus());
            $this->assertSame('insufficient_credits', $e->getErrorCode());
            $this->assertSame('55', $e->getRequestId());
        }

        try {
            $client->images->process('https://example.com/a.jpg', [['type' => 'resize', 'width' => 0, 'height' => 1]]);
            $this->fail('No exception.');
        } catch (InvalidRequestException $e) {
            $this->assertSame('operations.0.width', $e->getParam());
            $this->assertSame(['operations.0.width' => ['Bad width.']], $e->getErrors());
        }
    }

    public function test_wait_polls_until_the_request_finishes():void
    {
        $client = $this->client([
            new Response(200, [], '{"id":3,"status":"queued"}'),
            new Response(200, [], '{"id":3,"status":"processing","progress":40}'),
            new Response(200, [], '{"id":3,"status":"succeeded","result":{"files":[{"url":"https://r.test/x"}]}}'),
        ]);

        $done = $client->requests->wait(3);

        $this->assertSame('succeeded', $done['status']);
        $this->assertSame([1.0, 1.5], $this->slept);
        $this->assertSame('https://api.test/v1/requests/3', (string) $this->history[2]['request']->getUri());
    }

    public function test_wait_gives_up_after_the_timeout():void
    {
        $client = $this->client(array_fill(0, 5, new Response(200, [], '{"id":3,"status":"processing"}')));

        $this->expectException(TimeoutException::class);

        $client->requests->wait(3, 2);
    }

    public function test_listing_and_usage_send_their_query():void
    {
        $client = $this->client([new Response(200, [], '{"data":[]}'), new Response(200, [], '{"data":[]}')]);

        $client->requests->list(5, 100);
        $client->account->usage(['group_by' => 'endpoint']);

        $this->assertSame('limit=5&before=100', $this->history[0]['request']->getUri()->getQuery());
        $this->assertSame('group_by=endpoint', $this->history[1]['request']->getUri()->getQuery());
    }

    /**
     * @param array<int, Response> $responses
     *
     * @return Client
     */
    private function client(array $responses):Client
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new Client('wm_test_key', [
            'base_url'    => 'https://api.test',
            'http_client' => new Guzzle(['handler' => $stack]),
            'sleep'       => function (float $seconds) {
                $this->slept[] = $seconds;
            },
        ]);
    }
}
