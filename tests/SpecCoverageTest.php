<?php

namespace Watermark\Api\Tests;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Watermark\Api\Client;
use Watermark\Api\Exceptions\ApiException;

/**
 * Holds the client to the API's OpenAPI spec, so a library that is behind the
 * API fails here instead of in a customer's code.
 *
 * The spec comes from WATERMARK_API_SPEC (a path or a URL), else the API
 * checkout next to this one, else spec/openapi.json: the copy this library
 * was last checked against, whose sha256 is in spec.sha.
 */
class SpecCoverageTest extends TestCase
{
    private const LINK = 'link';

    /**
     * @var array{bytes: string, source: string, spec: array<string, mixed>}|null
     */
    private static ?array $loaded = null;

    /**
     * Every operation in the spec and the client method that calls it. A
     * "call" gets sample values for every body property and query parameter
     * the spec lists; the test checks the request it sent carries each one,
     * unless "allow" names it with the reason it is left out. "body" says how
     * the method builds the body: "pass-through" means its $options or
     * $changes array goes to the API as given.
     *
     * @return array<string, array{call?: \Closure(Client, array<string, mixed>, array<string, mixed>): mixed, body?: string, allow?: array<string, string>, link?: string}>
     */
    private static function coverage():array
    {
        $rest = fn (array $body, string ...$taken) => array_diff_key($body, array_flip($taken));

        return [
            'POST /v1/images/process'     => ['body' => 'pass-through', 'call' => fn (Client $c, array $b) => $c->images->process($b['source'], $b['operations'], $rest($b, 'source', 'operations'))],
            'POST /v1/images/read'        => ['body' => 'pass-through', 'call' => fn (Client $c, array $b) => $c->images->read($b['source'], $rest($b, 'source'))],
            'POST /v1/images/create'      => ['body' => 'pass-through', 'call' => fn (Client $c, array $b) => $c->images->create($b['width'], $b['height'], $b['operations'], $rest($b, 'width', 'height', 'operations'))],
            'POST /v1/videos/process'     => ['body' => 'pass-through', 'call' => fn (Client $c, array $b) => $c->videos->process($b['source'], $b['operations'], $rest($b, 'source', 'operations'))],
            'POST /v1/videos/read'        => ['body' => 'pass-through', 'call' => fn (Client $c, array $b) => $c->videos->read($b['source'], $rest($b, 'source'))],
            'POST /v1/videos/screenshot'  => ['body' => 'pass-through', 'call' => fn (Client $c, array $b) => $c->videos->screenshot($b['source'], $b['seconds'], $rest($b, 'source', 'seconds'))],
            'POST /v1/documents/process'  => ['body' => 'pass-through', 'call' => fn (Client $c, array $b) => $c->documents->process($b['source'], $b['operations'], $rest($b, 'source', 'operations'))],
            'POST /v1/documents/read'     => ['body' => 'pass-through', 'call' => fn (Client $c, array $b) => $c->documents->read($b['source'], $rest($b, 'source'))],
            'POST /v1/documents/snapshot' => ['body' => 'pass-through', 'call' => fn (Client $c, array $b) => $c->documents->snapshot($b['source'], $b['page'], $rest($b, 'source', 'page'))],
            'POST /v1/previews'           => ['body' => 'pass-through', 'call' => fn (Client $c, array $b) => $c->previews->create($b['source'], $b['operations'], $rest($b, 'source', 'operations'))],
            'POST /v1/batches'            => ['body' => 'pass-through', 'call' => fn (Client $c, array $b) => $c->batches->create($b['sources'], $rest($b, 'sources'))],
            'GET /v1/batches'             => ['call' => fn (Client $c, array $b, array $q) => $c->batches->list($q)],
            'GET /v1/batches/{id}'        => ['call' => fn (Client $c, array $b, array $q) => $c->batches->get(42, $q['wait'])],
            'GET /v1/batches/{id}/requests' => ['call' => fn (Client $c, array $b, array $q) => $c->batches->requests(42, $q)],
            'POST /v1/batches/{id}/retry' => ['call' => fn (Client $c) => $c->batches->retry(42)],
            'POST /v1/batches/{id}/abort' => ['call' => fn (Client $c) => $c->batches->abort(42)],
            'GET /v1/designs'             => ['call' => fn (Client $c, array $b, array $q) => $c->designs->list($q)],
            'POST /v1/designs'            => ['body' => 'pass-through', 'call' => fn (Client $c, array $b) => $c->designs->create($b)],
            'GET /v1/designs/{id}'        => ['call' => fn (Client $c) => $c->designs->get('dsn_4hT9kLm2PqR7sV1wX3yZ5a')],
            'PATCH /v1/designs/{id}'      => ['body' => 'pass-through', 'call' => fn (Client $c, array $b) => $c->designs->update('dsn_4hT9kLm2PqR7sV1wX3yZ5a', $b)],
            'DELETE /v1/designs/{id}'     => ['call' => fn (Client $c) => $c->designs->delete('dsn_4hT9kLm2PqR7sV1wX3yZ5a')],
            'GET /v1/requests'            => ['call' => fn (Client $c, array $b, array $q) => $c->requests->list($q['limit'], $q['before'])],
            'GET /v1/requests/{id}'       => ['call' => fn (Client $c, array $b, array $q) => $c->requests->get(1234, $q['wait'])],
            'POST /v1/requests/{id}/abort' => ['call' => fn (Client $c) => $c->requests->abort(1234)],
            'GET /v1/results/{id}'        => [self::LINK => 'A signed link the API returns in result.files[].url; opened as given, never built by the client.'],
            'GET /v1/files/{id}'          => ['call' => fn (Client $c) => $c->files->get('file_2kqV7PzM1aT4hQ0bR9sX3c')],
            'DELETE /v1/files/{id}'       => ['call' => fn (Client $c) => $c->files->delete('file_2kqV7PzM1aT4hQ0bR9sX3c')],
            'GET /v1/files/{id}/content'  => [self::LINK => 'A signed link the API returns as a file\'s url; opened as given, never built by the client.'],
            'POST /v1/uploads'            => ['body' => 'filename, size and content_type from the local file', 'call' => fn (Client $c) => $c->uploads->create(__FILE__)],
            'GET /v1/uploads/{id}'        => ['call' => fn (Client $c) => $c->uploads->get('upl_2kqV7PzM1aT4hQ0bR9sX3c')],
            'DELETE /v1/uploads/{id}'     => ['call' => fn (Client $c) => $c->uploads->delete('upl_2kqV7PzM1aT4hQ0bR9sX3c')],
            'GET /v1/storage'             => ['call' => fn (Client $c) => $c->storage->list()],
            'POST /v1/storage'            => ['body' => 'pass-through', 'call' => fn (Client $c, array $b) => $c->storage->connect($b)],
            'GET /v1/storage/{id}'        => ['call' => fn (Client $c) => $c->storage->get('stg_7a')],
            'PATCH /v1/storage/{id}'      => ['body' => 'pass-through', 'call' => fn (Client $c, array $b) => $c->storage->update('stg_7a', $b)],
            'DELETE /v1/storage/{id}'     => ['call' => fn (Client $c) => $c->storage->disconnect('stg_7a')],
            'POST /v1/storage/{id}/verify' => ['call' => fn (Client $c) => $c->storage->verify('stg_7a')],
            'GET /v1/events'              => ['call' => fn (Client $c, array $b, array $q) => $c->events->since($q['since'], $q['limit'])],
            'GET /v1/account'             => ['call' => fn (Client $c) => $c->account->get()],
            'GET /v1/usage'               => ['call' => fn (Client $c, array $b, array $q) => $c->account->usage($q)],
            'GET /v1/fonts'               => ['call' => fn (Client $c) => $c->fonts->list()],
            'GET /v1/operations'          => ['call' => fn (Client $c) => $c->account->operations()],
        ];
    }

    /**
     * How each field of the spec's error object reaches the caller.
     *
     * @return array<string, \Closure(ApiException): mixed>
     */
    private static function errorFields():array
    {
        return [
            'type'       => fn (ApiException $e) => $e->getType(),
            'code'       => fn (ApiException $e) => $e->getErrorCode(),
            'message'    => fn (ApiException $e) => $e->getMessage(),
            'param'      => fn (ApiException $e) => $e->getParam(),
            'request_id' => fn (ApiException $e) => $e->getRequestId(),
            'errors'     => fn (ApiException $e) => $e->getErrors(),
        ];
    }

    public function test_the_spec_is_the_one_this_library_was_last_checked_against():void
    {
        $recorded = trim((string) @file_get_contents(self::root() . '/spec.sha'));
        $copy = hash_file('sha256', self::root() . '/spec/openapi.json');

        $this->assertSame($recorded, $copy, 'spec.sha does not match spec/openapi.json. Run composer spec:refresh, which writes both.');

        ['bytes' => $bytes, 'source' => $source] = self::load();
        $actual = hash('sha256', $bytes);

        $this->assertSame($recorded, $actual, implode("\n", [
            "The API spec at {$source} (sha256 {$actual}) is not the one this library was last checked against (spec.sha {$recorded}).",
            "1. Review what changed: diff spec/openapi.json against {$source}.",
            '2. Update the library for it: new endpoints, parameters and errors, with tests and the README.',
            '3. Then run composer spec:refresh to record the spec as checked.',
        ]));
    }

    public function test_every_operation_in_the_spec_has_a_client_method_and_the_map_nothing_stale():void
    {
        $inSpec = array_keys(self::operations());
        $mapped = array_keys(self::coverage());

        $this->assertSame([], array_values(array_diff($inSpec, $mapped)), 'These operations have no client method: add one, and map it in ' . self::class . '::coverage().');
        $this->assertSame([], array_values(array_diff($mapped, $inSpec)), 'The spec no longer has these operations: remove them from the client and from ' . self::class . '::coverage().');
    }

    public function test_every_body_property_and_query_parameter_is_sent_or_allowed_with_a_reason():void
    {
        $problems = [];

        foreach (self::operations() as $key => $operation) {
            $entry = self::coverage()[$key] ?? null;

            if ($entry === null || isset($entry[self::LINK])) {
                continue;
            }

            [$method, $path] = explode(' ', $key, 2);
            $body = array_map(fn ($schema) => self::sample($schema), $operation['body']);
            $query = array_map(fn ($param) => self::sample($param['schema'] ?? [], $param), $operation['query']);

            $history = [];
            ($entry['call'])($this->recordingClient($history), $body, $query);

            $sent = $this->find($history, $method, $path);

            if ($sent === null) {
                $problems[] = "{$key}: the mapped call sent no {$method} to {$path}.";

                continue;
            }

            parse_str($sent->getUri()->getQuery(), $sentQuery);
            $sentBody = json_decode((string) $sent->getBody(), true) ?: [];
            $allowed = $entry['allow'] ?? [];

            $expected = [
                'body property'   => [array_keys($operation['body']), array_keys($sentBody)],
                'query parameter' => [array_keys($operation['query']), array_keys($sentQuery)],
                'header'          => [$operation['headers'], array_keys(array_filter(array_flip($operation['headers']), fn ($_, $name) => $sent->hasHeader($name), ARRAY_FILTER_USE_BOTH))],
            ];

            foreach ($expected as $what => [$names, $present]) {
                foreach (array_diff($names, $present, array_keys($allowed)) as $name) {
                    $problems[] = "{$key}: the {$what} {$name} is not sent. Send it, or allow it in coverage() with the reason.";
                }

                foreach (array_intersect(array_keys($allowed), $present) as $name) {
                    $problems[] = "{$key}: {$name} is sent now; take it off the allow-list.";
                }
            }

            $known = array_merge(array_keys($operation['body']), array_keys($operation['query']), $operation['headers']);

            foreach (array_diff(array_keys($allowed), $known) as $name) {
                $problems[] = "{$key}: the allow-list names {$name}, which the spec no longer has.";
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    public function test_every_field_and_type_of_an_error_reaches_the_caller():void
    {
        $schema = self::resolve(self::load()['spec']['components']['schemas']['Error'])['properties']['error'];
        $fields = $schema['properties'];
        $error = array_map(fn ($field) => self::sample($field), $fields);
        $error['type'] = 'invalid_request';

        $exception = ApiException::fromResponse(422, ['error' => $error]);

        foreach ($fields as $name => $_) {
            $accessor = self::errorFields()[$name] ?? fn (ApiException $e) => $e->getError()[$name] ?? null;

            $this->assertSame($error[$name], $accessor($exception), "The error field {$name} does not reach the caller: give it an accessor, or keep it in getError().");
        }

        foreach ($fields['type']['enum'] as $type) {
            $exception = ApiException::fromResponse(400, ['error' => ['type' => $type, 'code' => 'x', 'message' => 'x']]);

            $this->assertSame($type, $exception->getType());

            if ($type !== 'server_error') {
                $this->assertNotSame(ApiException::class, get_class($exception), "The error type {$type} has no exception class in ApiException::fromResponse().");
            }
        }
    }

    public function test_the_readme_covers_every_error_code():void
    {
        $readme = (string) file_get_contents(self::root() . '/README.md');
        $this->assertMatchesRegularExpression('/^## Errors$/m', $readme, 'The README has no "## Errors" section.');

        $section = preg_split('/^## /m', explode("\n## Errors\n", $readme, 2)[1])[0];

        if (preg_match('/passes every error code through/i', $section)) {
            $this->addToAssertionCount(1);

            return;
        }

        $missing = array_filter(self::errorCodes(), fn ($code) => ! str_contains($section, "`{$code}`"));

        $this->assertSame([], array_values($missing), 'The README\'s Errors section neither names these codes nor says the client passes every error code through.');
    }

    /**
     * @return array<string, array{body: array<string, array<string, mixed>>, query: array<string, array<string, mixed>>, headers: string[]}>
     */
    private static function operations():array
    {
        $spec = self::load()['spec'];
        $operations = [];

        foreach ($spec['paths'] as $path => $item) {
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                if (! isset($item[$method])) {
                    continue;
                }

                $operation = $item[$method];
                $parameters = array_map(fn ($param) => self::resolve($param), array_merge($item['parameters'] ?? [], $operation['parameters'] ?? []));
                $schema = self::resolve(self::resolve($operation['requestBody'] ?? [])['content']['application/json']['schema'] ?? []);

                $operations[strtoupper($method) . ' ' . $path] = [
                    'body'    => $schema['properties'] ?? [],
                    'query'   => array_column(array_filter($parameters, fn ($param) => $param['in'] === 'query'), null, 'name'),
                    'headers' => array_column(array_filter($parameters, fn ($param) => $param['in'] === 'header'), 'name'),
                ];
            }
        }

        return $operations;
    }

    /**
     * The error codes the spec lists: an enum or x-error-codes on the error's
     * code, and, when the spec is the API's own build, the tables of the
     * errors guide built beside it.
     *
     * @return string[]
     */
    private static function errorCodes():array
    {
        ['spec' => $spec, 'source' => $source] = self::load();
        $code = self::resolve($spec['components']['schemas']['Error'])['properties']['error']['properties']['code'] ?? [];
        $codes = array_merge($code['enum'] ?? [], $code['x-error-codes'] ?? []);

        $guide = dirname($source) . '/pages/errors.md';

        if (! preg_match('#^https?://#', $source) && is_file($guide)) {
            $column = null;

            foreach (file($guide) as $line) {
                if (! str_starts_with($line, '|')) {
                    $column = null;

                    continue;
                }

                $cells = array_map('trim', explode('|', trim($line, "| \n")));

                if ($column === null) {
                    $column = array_search(true, array_map(fn ($cell) => in_array($cell, ['Code', 'Codes'], true), $cells), true);

                    continue;
                }

                if ($column !== false && preg_match_all('/`([a-z]+(?:_[a-z]+)*)`/', $cells[$column] ?? '', $matches)) {
                    $codes = array_merge($codes, $matches[1]);
                }
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * A value of the schema's type, for a call that must send every field.
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $holder The parameter, whose example wins.
     *
     * @return mixed
     */
    private static function sample(array $schema, array $holder = []):mixed
    {
        $schema = self::resolve($schema);

        if (array_key_exists('example', $holder)) {
            return $holder['example'];
        }

        if (array_key_exists('example', $schema)) {
            return $schema['example'];
        }

        if (isset($schema['enum'])) {
            return $schema['enum'][0];
        }

        if (isset($schema['oneOf']) || isset($schema['anyOf'])) {
            return self::sample(($schema['oneOf'] ?? $schema['anyOf'])[0]);
        }

        $type = is_array($schema['type'] ?? null) ? $schema['type'][0] : ($schema['type'] ?? 'string');

        return match ($type) {
            'integer', 'number' => 1,
            'boolean'           => true,
            'array'             => [self::sample($schema['items'] ?? [])],
            'object'            => ['key' => 'value'],
            default             => 'value',
        };
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    private static function resolve(array $node):array
    {
        while (isset($node['$ref'])) {
            $target = self::load()['spec'];

            foreach (explode('/', substr($node['$ref'], 2)) as $part) {
                $target = $target[$part];
            }

            $node = $target;
        }

        return $node;
    }

    /**
     * @return array{bytes: string, source: string, spec: array<string, mixed>}
     */
    private static function load():array
    {
        if (self::$loaded !== null) {
            return self::$loaded;
        }

        $sibling = dirname(self::root()) . '/watermark-api/resources/docs/build/openapi.json';
        $source = getenv('WATERMARK_API_SPEC') ?: (is_file($sibling) ? realpath($sibling) : self::root() . '/spec/openapi.json');
        $bytes = @file_get_contents($source);

        if ($bytes === false) {
            self::fail("Cannot read the API spec at {$source}.");
        }

        return self::$loaded = ['bytes' => $bytes, 'source' => $source, 'spec' => json_decode($bytes, true, 512, JSON_THROW_ON_ERROR)];
    }

    private static function root():string
    {
        return dirname(__DIR__);
    }

    /**
     * A client whose every call succeeds with a body that fits any endpoint,
     * and which records what it sent.
     *
     * @param array<int, array{request: RequestInterface}> $history
     *
     * @return Client
     */
    private function recordingClient(array &$history):Client
    {
        $body = json_encode(['id' => 1234, 'status' => 'succeeded', 'upload' => ['url' => 'https://uploads.test/put', 'headers' => []], 'data' => []]);
        $stack = HandlerStack::create(fn () => Create::promiseFor(new Response(200, [], $body)));
        $stack->push(Middleware::history($history));

        return new Client('wm_test_key', [
            'base_url'    => 'https://api.test',
            'http_client' => new Guzzle(['handler' => $stack]),
            'sleep'       => fn () => null,
        ]);
    }

    /**
     * @param array<int, array{request: RequestInterface}> $history
     * @param string                                      $method
     * @param string                                      $path   With {placeholders}.
     *
     * @return RequestInterface|null
     */
    private function find(array $history, string $method, string $path):?RequestInterface
    {
        $pattern = '#^' . preg_replace('#\\\\\{[^/]+?\\\\\}#', '[^/]+', preg_quote($path, '#')) . '$#';

        foreach ($history as $entry) {
            $request = $entry['request'];

            if ($request->getMethod() === $method && $request->getUri()->getHost() === 'api.test' && preg_match($pattern, $request->getUri()->getPath())) {
                return $request;
            }
        }

        return null;
    }
}
