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
     * @param string|array<string, string>                          $source A URL, an upload (upl_…), a file (file_…) or {"storage": "stg_…", "key": "…"}.
     * @param array<int, array<string, mixed>> $operations Such as [['type' => 'resize', 'width' => 800, 'height' => 600]].
     * @param array<string, mixed>             $options    output (['format' => 'webp']), webhook_url, customer, metadata, idempotency_key.
     *
     * @return array<string, mixed> The request.
     */
    public function process(string|array $source, array $operations, array $options = []):array
    {
        return Endpoint::post($this->transport, '/v1/images/process', ['source' => $source, 'operations' => $operations], $options);
    }

    /**
     * Make an image from nothing: a blank canvas, then operations such as text.
     *
     * @param int                              $width
     * @param int                              $height
     * @param array<int, array<string, mixed>> $operations Such as [['type' => 'text', 'text' => 'Hello', 'font' => 'inter-bold', 'size' => 96]].
     * @param array<string, mixed>             $options    background ("transparent" or "#ffffff"), outputs, wait, webhook_url, customer, metadata, idempotency_key.
     *
     * @return array<string, mixed> The request.
     */
    public function create(int $width, int $height, array $operations, array $options = []):array
    {
        return Endpoint::post($this->transport, '/v1/images/create', ['width' => $width, 'height' => $height, 'operations' => $operations], $options);
    }

    /**
     * Read an image's dimensions, format and colorspace.
     *
     * @param string|array<string, string>               $source A URL, an upload (upl_…), a file (file_…) or {"storage": "stg_…", "key": "…"}.
     * @param array<string, mixed> $options webhook_url, customer, metadata, idempotency_key.
     *
     * @return array<string, mixed> The request.
     */
    public function read(string|array $source, array $options = []):array
    {
        return Endpoint::post($this->transport, '/v1/images/read', ['source' => $source], $options);
    }
}
