# Watermark API for PHP

Watermark, resize and convert images, videos and PDFs with the [Watermark API](https://api.watermark.ws/docs).

```bash
composer require watermark/watermark-php
```

Requires PHP 8.1+. Get a key at https://watermark.ws/developers (`wm_test_…` keys are free).

## Process an image

```php
use Watermark\Api\Client;

$client = new Client(getenv('WATERMARK_API_KEY'));

$request = $client->images->process('https://example.com/photo.jpg', [
    ['type' => 'resize', 'width' => 1600, 'height' => 1600],
    ['type' => 'watermark', 'image_url' => 'https://example.com/logo.png', 'x' => 40, 'y' => 40],
], ['output' => ['format' => 'webp']]);

$done = $client->requests->wait($request['id']);   // polls until succeeded, failed or aborted

if ($done['status'] === 'succeeded') {
    $url = $done['result']['files'][0]['url'];      // signed, valid for an hour
}
```

Every processing call starts a background request and returns it (`status: queued`). Options on every call: `webhook_url`, `customer`, `metadata`, `idempotency_key`; processing calls also take `output` (`['format' => 'png']`).

| Call | Endpoint |
| --- | --- |
| `$client->images->process($source, $operations, $options)` | `POST /v1/images/process` |
| `$client->images->read($source)` | `POST /v1/images/read` |
| `$client->videos->process($source, $operations, $options)` | `POST /v1/videos/process` |
| `$client->videos->read($source)` / `->screenshot($source, $seconds)` | `POST /v1/videos/read`, `/screenshot` |
| `$client->documents->process($source, $operations)` | `POST /v1/documents/process` |
| `$client->documents->read($source)` / `->snapshot($source, $page)` | `POST /v1/documents/read`, `/snapshot` |
| `$client->requests->get($id)` / `->list()` / `->abort($id)` / `->wait($id)` | `/v1/requests` |
| `$client->account->get()` / `->usage([...])` / `->operations()` | `/v1/account`, `/v1/usage`, `/v1/operations` |

Operations and their parameters: https://api.watermark.ws/docs/operations.md

## Errors

API errors throw `Watermark\Api\Exceptions\ApiException` subclasses (`InvalidRequestException`, `PaymentRequiredException`, `RateLimitException`, ...). Branch on `getErrorCode()`; `getParam()` names the input at fault. A failed *request* is not an exception: its `status` is `failed` and `error` says why.

The client retries 429s (after `Retry-After`), 5xx responses and dropped connections twice, sending the same `Idempotency-Key`, so a retry never runs a request twice.

## Webhooks

```php
use Watermark\Api\Webhook;

$event = Webhook::constructEvent(
    file_get_contents('php://input'),
    $_SERVER['HTTP_WATERMARK_SIGNATURE'] ?? '',
    getenv('WATERMARK_WEBHOOK_SECRET'),
);   // throws WebhookSignatureException when the signature is wrong or older than 5 minutes
```

## Development

```bash
composer install
vendor/bin/phpunit
```
