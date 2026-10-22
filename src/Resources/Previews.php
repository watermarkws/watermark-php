<?php

namespace Watermark\Api\Resources;

use Watermark\Api\Transport;

/**
 * One small picture of what operations, or a template, will do to a file,
 * before you run them on it or on a batch. A preview is an ordinary request
 * (endpoint "previews") that the API holds for up to 20 seconds and usually
 * finishes within the call; result.files[0] is a WebP kept for a day and
 * result.data.layout says where each watermark and text landed.
 *
 *     $preview = $client->previews->create('https://example.com/photo.jpg', [
 *         ['type' => 'watermark', 'image_url' => 'https://example.com/logo.png', 'anchor' => 'bottom_right', 'margin' => '3%', 'size' => '18%'],
 *     ], ['width' => 800]);
 */
class Previews
{
    public function __construct(private Transport $transport)
    {
    }

    /**
     * @param string|array<string, string>     $source     A URL, an upload (upl_…), a file (file_…) or {"storage": "stg_…", "key": "…"}.
     * @param array<int, array<string, mixed>> $operations The operations to show; may be empty when a template is given.
     * @param array<string, mixed>             $options    template (tpl_…), media (image, video or document), width (64-1600),
     *                                                     at (seconds, for a video), page (from 0, for a document), wait,
     *                                                     webhook_url, events, customer, metadata, idempotency_key.
     *
     * @return array<string, mixed> The request; wait for it with $client->requests->wait() when its status is not final.
     */
    public function create(string|array $source, array $operations, array $options = []):array
    {
        return Endpoint::post($this->transport, '/v1/previews', ['source' => $source, 'operations' => $operations], $options);
    }
}
