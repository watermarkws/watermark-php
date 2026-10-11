<?php

namespace Watermark\Api\Resources;

use Watermark\Api\Transport;

/**
 * PDF endpoints.
 */
class Documents
{
    public function __construct(private Transport $transport)
    {
    }

    /**
     * Run operations on every page.
     *
     * @param string                           $source
     * @param array<int, array<string, mixed>> $operations
     * @param array<string, mixed>             $options max_pages, webhook_url, customer, metadata, idempotency_key.
     *
     * @return array<string, mixed> The request.
     */
    public function process(string $source, array $operations, array $options = []):array
    {
        return Endpoint::post($this->transport, '/v1/documents/process', ['source' => $source, 'operations' => $operations], $options);
    }

    /**
     * @param string               $source
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed> The request.
     */
    public function read(string $source, array $options = []):array
    {
        return Endpoint::post($this->transport, '/v1/documents/read', ['source' => $source], $options);
    }

    /**
     * Render one page (from 1) as an image.
     *
     * @param string               $source
     * @param int                  $page
     * @param array<string, mixed> $options output (['format' => 'png']), webhook_url, ...
     *
     * @return array<string, mixed> The request.
     */
    public function snapshot(string $source, int $page = 1, array $options = []):array
    {
        return Endpoint::post($this->transport, '/v1/documents/snapshot', ['source' => $source, 'page' => $page], $options);
    }
}
