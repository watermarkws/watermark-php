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
     * Run operations on a video, in order, and save an MP4.
     *
     * @param string                           $source
     * @param array<int, array<string, mixed>> $operations
     * @param array<string, mixed>             $options max_duration, output, webhook_url, customer, metadata, idempotency_key.
     *
     * @return array<string, mixed> The request.
     */
    public function process(string $source, array $operations, array $options = []):array
    {
        return Endpoint::post($this->transport, '/v1/videos/process', ['source' => $source, 'operations' => $operations], $options);
    }

    /**
     * @param string               $source
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed> The request.
     */
    public function read(string $source, array $options = []):array
    {
        return Endpoint::post($this->transport, '/v1/videos/read', ['source' => $source], $options);
    }

    /**
     * Save one frame as an image.
     *
     * @param string               $source
     * @param int                  $seconds From the start.
     * @param array<string, mixed> $options output (['format' => 'png']), webhook_url, ...
     *
     * @return array<string, mixed> The request.
     */
    public function screenshot(string $source, int $seconds, array $options = []):array
    {
        return Endpoint::post($this->transport, '/v1/videos/screenshot', ['source' => $source, 'seconds' => $seconds], $options);
    }
}
