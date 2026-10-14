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

    public function test_a_storage_change_whose_check_failed_carries_the_policy_it_needs():void
    {
        $policy = ['Version' => '2012-10-17', 'Statement' => [['Effect' => 'Allow', 'Action' => ['s3:GetObject'], 'Resource' => ['arn:aws:s3:::b/archive/*']]]];

        $client = $this->client([
            new Response(422, [], json_encode(['error' => ['type' => 'invalid_request', 'code' => 'destination_rejected', 'message' => 'The connection was not changed.', 'permissions_policy' => $policy]])),
        ]);

        try {
            $client->storage->update('stg_7a', ['prefixes' => ['archive/']]);
            $this->fail('No exception.');
        } catch (InvalidRequestException $e) {
            $this->assertSame('destination_rejected', $e->getErrorCode());
            $this->assertSame($policy, $e->getError()['permissions_policy']);
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
        $this->assertSame('https://api.test/v1/requests/3?wait=25', (string) $this->history[2]['request']->getUri());
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

    public function test_create_makes_an_image_from_a_canvas_and_a_source_can_be_a_storage_object():void
    {
        $client = $this->client([new Response(202, [], '{"id":4}'), new Response(202, [], '{"id":5}')]);

        $client->images->create(1200, 300, [['type' => 'text', 'text' => 'Hi', 'font' => 'inter-bold', 'size' => 96]], ['background' => '#ffffff', 'wait' => 10]);
        $client->videos->process(['storage' => 'stg_7a', 'key' => 'in/clip.mov'], [['type' => 'grayscale']]);

        $this->assertSame('https://api.test/v1/images/create', (string) $this->history[0]['request']->getUri());
        $this->assertSame(['width' => 1200, 'height' => 300, 'operations' => [['type' => 'text', 'text' => 'Hi', 'font' => 'inter-bold', 'size' => 96]], 'background' => '#ffffff', 'wait' => 10], json_decode((string) $this->history[0]['request']->getBody(), true));
        $this->assertSame(['storage' => 'stg_7a', 'key' => 'in/clip.mov'], json_decode((string) $this->history[1]['request']->getBody(), true)['source']);
    }

    public function test_an_upload_is_made_and_the_file_put_to_its_url():void
    {
        $path = tempnam(sys_get_temp_dir(), 'wm') . '.png';
        file_put_contents($path, 'not really a png');

        $client = $this->client([
            new Response(201, [], json_encode(['id' => 'upl_1', 'status' => 'pending', 'upload' => ['method' => 'PUT', 'url' => 'https://bucket.test/u/1?sig=x', 'headers' => ['Content-Type' => 'image/png', 'x-amz-meta-a' => 'b']]])),
            new Response(200),
        ]);

        try {
            $upload = $client->uploads->create($path);
        } finally {
            unlink($path);
        }

        $this->assertSame('upl_1', $upload['id']);
        $this->assertSame(['filename' => basename($path), 'size' => 16, 'content_type' => 'image/png'], json_decode((string) $this->history[0]['request']->getBody(), true));

        $put = $this->history[1]['request'];

        $this->assertSame('PUT', $put->getMethod());
        $this->assertSame('https://bucket.test/u/1?sig=x', (string) $put->getUri());
        $this->assertSame('b', $put->getHeaderLine('x-amz-meta-a'));
        $this->assertSame('', $put->getHeaderLine('Authorization'));
        $this->assertSame('not really a png', (string) $put->getBody());
    }

    public function test_an_upload_of_an_svg_or_a_camera_raw_file_names_its_type():void
    {
        foreach (['svg' => 'image/svg+xml', 'nef' => 'image/x-nikon-nef', 'wmv' => 'video/x-ms-wmv'] as $extension => $type) {
            $path = tempnam(sys_get_temp_dir(), 'wm') . ".{$extension}";
            file_put_contents($path, 'contents');
            $this->history = [];

            $client = $this->client([
                new Response(201, [], json_encode(['id' => 'upl_1', 'status' => 'pending', 'upload' => ['method' => 'PUT', 'url' => 'https://bucket.test/u/1?sig=x', 'headers' => ['Content-Type' => $type]]])),
                new Response(200),
            ]);

            try {
                $client->uploads->create($path);
            } finally {
                unlink($path);
            }

            $this->assertSame($type, json_decode((string) $this->history[0]['request']->getBody(), true)['content_type'], $extension);
        }
    }

    public function test_files_storage_events_and_fonts_call_their_endpoints():void
    {
        $client = $this->client(array_fill(0, 9, new Response(200, [], '{}')));

        $client->files->get('file_1');
        $client->files->delete('file_1');
        $client->storage->connect(['bucket' => 'b', 'region' => 'us-east-1', 'role_arn' => 'arn:aws:iam::1:role/r']);
        $client->storage->update('stg_7a', ['prefixes' => ['in/', 'archive/']]);
        $client->storage->verify('stg_7a');
        $client->storage->disconnect('stg_7a');
        $client->events->since(new \DateTimeImmutable('2026-10-13T15:00:00Z'), 50);
        $client->fonts->list();
        $client->uploads->delete('upl_1');

        $calls = array_map(fn (array $entry) => $entry['request']->getMethod() . ' ' . $entry['request']->getUri()->getPath(), $this->history);

        $this->assertSame([
            'GET /v1/files/file_1',
            'DELETE /v1/files/file_1',
            'POST /v1/storage',
            'PATCH /v1/storage/stg_7a',
            'POST /v1/storage/stg_7a/verify',
            'DELETE /v1/storage/stg_7a',
            'GET /v1/events',
            'GET /v1/fonts',
            'DELETE /v1/uploads/upl_1',
        ], $calls);
        $this->assertSame('since=2026-10-13T15%3A00%3A00%2B00%3A00&limit=50', $this->history[6]['request']->getUri()->getQuery());
        $this->assertSame(['prefixes' => ['in/', 'archive/']], json_decode((string) $this->history[3]['request']->getBody(), true));
        $this->assertSame('s3', json_decode((string) $this->history[2]['request']->getBody(), true)['provider']);
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
