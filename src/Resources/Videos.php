<?php

namespace Watermark\Api\Resources;

use Watermark\Api\Transport;

/**
 * Video endpoints, for accounts that have bought credit.
 */
class Videos
{
    public function __construct(private Transport $transport)
    {
    }

    /**
     * Run operations on a video, in order. The output keeps an MP4, or takes outputs[0].format (mov, webm, ...).
     *
     * @param string|array<string, string>                           $source A URL, an upload (upl_…), a file (file_…) or {"storage": "stg_…", "key": "…"}.
     * @param array<int, array<string, mixed>> $operations
     * @param array<string, mixed>             $options max_duration, output, webhook_url, customer, metadata, idempotency_key.
     *
     * @return array<string, mixed> The request.
     */
    public function process(string|array $source, array $operations, array $options = []):array
    {
        return Endpoint::post($this->transport, '/v1/videos/process', ['source' => $source, 'operations' => $operations], $options);
    }

    /**
     * @param string|array<string, string>               $source A URL, an upload (upl_…), a file (file_…) or {"storage": "stg_…", "key": "…"}.
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed> The request.
     */
    public function read(string|array $source, array $options = []):array
    {
        return Endpoint::post($this->transport, '/v1/videos/read', ['source' => $source], $options);
    }

    /**
     * Save one frame as an image.
     *
     * @param string|array<string, string>               $source A URL, an upload (upl_…), a file (file_…) or {"storage": "stg_…", "key": "…"}.
     * @param int                  $seconds From the start.
     * @param array<string, mixed> $options output (['format' => 'png']), webhook_url, ...
     *
     * @return array<string, mixed> The request.
     */
    public function screenshot(string|array $source, int $seconds, array $options = []):array
    {
        return Endpoint::post($this->transport, '/v1/videos/screenshot', ['source' => $source, 'seconds' => $seconds], $options);
    }
}
