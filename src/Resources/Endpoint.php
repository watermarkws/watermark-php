<?php

namespace Watermark\Api\Resources;

use Watermark\Api\Transport;

/**
 * @internal
 */
final class Endpoint
{
    /**
     * POST a processing call: the body plus the caller's options, with the
     * idempotency key sent as its header. An empty operations list is left
     * out, since a call with a template needs none of its own.
     *
     * @param Transport            $transport
     * @param string               $path
     * @param array<string, mixed> $body
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public static function post(Transport $transport, string $path, array $body, array $options):array
    {
        $idempotencyKey = $options['idempotency_key'] ?? null;
        unset($options['idempotency_key']);

        if (($body['operations'] ?? null) === []) {
            unset($body['operations']);
        }

        return $transport->request('POST', $path, $body + $options, [], $idempotencyKey);
    }
}
