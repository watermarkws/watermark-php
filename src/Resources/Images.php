<?php

namespace Watermark\Api\Resources;

use Watermark\Api\Transport;

/**
 * Image endpoints. Every call starts a request and returns it, queued; wait
 * for it with $client->requests->wait($request['id']).
 */
class Images
{
    public function __construct(private Transport $transport)
    {
    }

    /**
     * Run operations on an image, in order.
     *
     * @param string                          $source     A public http(s) URL.
     * @param array<int, array<string, mixed>> $operations Such as [['type' => 'resize', 'width' => 800, 'height' => 600]].
     * @param array<string, mixed>             $options    output (['format' => 'webp']), webhook_url, customer, metadata, idempotency_key.
     *
     * @return array<string, mixed> The request.
     */
    public function process(string $source, array $operations, array $options = []):array
    {
        return Endpoint::post($this->transport, '/v1/images/process', ['source' => $source, 'operations' => $operations], $options);
    }

    /**
     * Read an image's dimensions, format and colorspace.
     *
     * @param string               $source
     * @param array<string, mixed> $options webhook_url, customer, metadata, idempotency_key.
     *
     * @return array<string, mixed> The request.
     */
    public function read(string $source, array $options = []):array
    {
        return Endpoint::post($this->transport, '/v1/images/read', ['source' => $source], $options);
    }
}
