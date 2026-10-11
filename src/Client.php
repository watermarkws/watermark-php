<?php

namespace Watermark\Api;

use Watermark\Api\Resources\Account;
use Watermark\Api\Resources\Documents;
use Watermark\Api\Resources\Images;
use Watermark\Api\Resources\Requests;
use Watermark\Api\Resources\Videos;

/**
 * The Watermark API.
 *
 *     $client = new Client(getenv('WATERMARK_API_KEY'));
 *     $request = $client->images->process('https://example.com/photo.jpg', [
 *         ['type' => 'resize', 'width' => 1600, 'height' => 1600],
 *         ['type' => 'watermark', 'image_url' => 'https://example.com/logo.png', 'x' => 40, 'y' => 40],
 *     ], ['output' => ['format' => 'webp']]);
 *     $done = $client->requests->wait($request['id']);
 *     $url = $done['result']['files'][0]['url'];
 */
class Client
{
    public readonly Images $images;
    public readonly Videos $videos;
    public readonly Documents $documents;
    public readonly Requests $requests;
    public readonly Account $account;

    /**
     * @param string               $apiKey  wm_live_… or wm_test_…
     * @param array<string, mixed> $options base_url, timeout (seconds), max_retries, http_client (a Guzzle ClientInterface).
     */
    public function __construct(string $apiKey, array $options = [])
    {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('An API key is required: https://watermark.ws/developers');
        }

        $sleep = $options['sleep'] ?? fn (float $seconds) => usleep((int) ($seconds * 1_000_000));
        $transport = new Transport($apiKey, ['sleep' => $sleep] + $options);

        $this->images = new Images($transport);
        $this->videos = new Videos($transport);
        $this->documents = new Documents($transport);
        $this->requests = new Requests($transport, \Closure::fromCallable($sleep));
        $this->account = new Account($transport);
    }
}
