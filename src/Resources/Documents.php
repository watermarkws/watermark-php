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
     * @param string|array<string, string>                           $source A URL, an upload (upl_…), a file (file_…) or {"storage": "stg_…", "key": "…"}.
     * @param array<int, array<string, mixed>> $operations
     * @param array<string, mixed>             $options max_pages, webhook_url, customer, metadata, idempotency_key.
     *
     * @return array<string, mixed> The request.
     */
    public function process(string|array $source, array $operations, array $options = []):array
    {
        return Endpoint::post($this->transport, '/v1/documents/process', ['source' => $source, 'operations' => $operations], $options);
    }

    /**
     * @param string|array<string, string>               $source A URL, an upload (upl_…), a file (file_…) or {"storage": "stg_…", "key": "…"}.
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed> The request.
     */
    public function read(string|array $source, array $options = []):array
    {
        return Endpoint::post($this->transport, '/v1/documents/read', ['source' => $source], $options);
    }

    /**
     * Render one page (from 1) as an image.
     *
     * @param string|array<string, string>               $source A URL, an upload (upl_…), a file (file_…) or {"storage": "stg_…", "key": "…"}.
     * @param int                  $page
     * @param array<string, mixed> $options output (['format' => 'png']), webhook_url, ...
     *
     * @return array<string, mixed> The request.
     */
    public function snapshot(string|array $source, int $page = 1, array $options = []):array
    {
        return Endpoint::post($this->transport, '/v1/documents/snapshot', ['source' => $source, 'page' => $page], $options);
    }
}
