# Watermark API for PHP

Watermark, resize and convert images, videos and PDFs with the [Watermark API](https://api.watermark.ws/docs).

```bash
composer require watermarkws/watermark-php
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
    $url = $done['result']['files'][0]['url'];      // works until the result is deleted, 7 days on
}
```

Every processing call starts a background request and returns it (`status: queued`). Options on every call: `webhook_url`, `events`, `customer`, `metadata`, `wait` (seconds the API may hold the answer until the request finishes), `idempotency_key`; calls that make files also take `output` (`['format' => 'png']`) or `outputs`, `process()` and `create()` take `template` (a saved template's operations run first, and the call's own may then be `[]`), and `$client->images->read()` takes `level`. The options array goes to the API as given, so a parameter the API adds later works without an update.

A `watermark` operation is placed by `anchor`, `margin` and `size` as percentages of the file, which lands the same on every size of file; percentages are strings (`'3%'`), pixels are numbers.

## A folder of files in one batch

```php
$batch = $client->batches->create([
    'https://example.com/photos/IMG_4471.jpg',
    'upl_9f3Tq1LmA2bC4dE6fG8hJ0',                         // an upload, from $client->uploads->create()
    ['storage' => 'stg_7a', 'prefix' => 'photos/shoot/'],  // every object under a prefix of your bucket
], [
    'operations' => [['type' => 'watermark', 'image_url' => 'https://example.com/logo.png', 'anchor' => 'bottom_right', 'margin' => '3%', 'size' => '18%', 'opacity' => 60]],
    'output'     => ['format' => 'webp'],
    'zip'        => true,
]);

echo $batch['price']['estimate_micro_usd'];          // what the whole batch will cost, in millionths of a dollar

$done = $client->batches->wait($batch['id'], 3600);  // long-polls GET /v1/batches/{id}?wait=25 until succeeded, partial, failed or aborted

foreach ($done['zips'] ?? [] as $zip) {
    copy($zip['url'], "{$zip['name']}.zip");         // a signed link, no key needed, valid 7 days
}

if ($done['status'] === 'partial') {
    $failed = $client->batches->requests($done['id'], ['status' => 'failed']);   // each with its error
    $client->batches->retry($done['id']);                                        // once the cause is fixed
}
```

`create()` takes `media` (`image` unless sent, `video` or `document`), `template` and/or `operations`, `output` or `outputs`, `destination` (`['storage' => 'stg_…', 'prefix' => 'done/']`, instead of `zip`), `max_duration`, `max_pages`, `webhook_url`, `events` (`batch.finished`, `batch.progress`), `request_events`, `customer`, `metadata` and `idempotency_key`. Each file is an ordinary request with a `batch` and a `position`.

## A template, and a preview before the run

```php
$template = $client->templates->create([
    'name'       => 'Studio corner logo',
    'operations' => [['type' => 'watermark', 'image' => $upload['id'], 'anchor' => 'bottom_right', 'margin' => '3%', 'size' => '18%']],
]);   // the upload becomes an asset of the template, kept as long as it is

$preview = $client->previews->create('https://example.com/photo.jpg', [], ['template' => $template['id'], 'width' => 800]);
$picture = $preview['result']['files'][0]['url'];         // a WebP, kept a day; result.data.layout says where the logo landed

$client->images->process('https://example.com/photo.jpg', [['type' => 'resize', 'width' => 1600, 'height' => 1600]], ['template' => $template['id']]);
```

A preview is a request the API usually finishes within the call (`status: succeeded`); when it comes back `queued`, wait for it with `$client->requests->wait()`. It takes `media`, `width` (64 to 1600), `at` (seconds, for a video) and `page` (from 0, for a document).

| Call | Endpoint |
| --- | --- |
| `$client->images->process($source, $operations, $options)` | `POST /v1/images/process` |
| `$client->images->read($source)` | `POST /v1/images/read` |
| `$client->images->create($width, $height, $operations, $options)` | `POST /v1/images/create` |
| `$client->videos->process($source, $operations, $options)` | `POST /v1/videos/process` |
| `$client->videos->read($source)` / `->screenshot($source, $seconds)` | `POST /v1/videos/read`, `/screenshot` |
| `$client->documents->process($source, $operations)` | `POST /v1/documents/process` |
| `$client->documents->read($source)` / `->snapshot($source, $page)` | `POST /v1/documents/read`, `/snapshot` |
| `$client->previews->create($source, $operations, $options)` | `POST /v1/previews` |
| `$client->batches->create($sources, $options)` | `POST /v1/batches` |
| `$client->batches->list($query)` / `->get($id, $wait)` / `->requests($id, $query)` | `GET /v1/batches`, `/v1/batches/{id}`, `/v1/batches/{id}/requests` |
| `$client->batches->retry($id)` / `->abort($id)` | `POST /v1/batches/{id}/retry`, `/abort` |
| `$client->batches->wait($id, $timeout)` | Polls `GET /v1/batches/{id}` |
| `$client->templates->create([...])` / `->list($query)` / `->get($id)` / `->update($id, [...])` / `->delete($id)` | `/v1/templates` |
| `$client->requests->get($id)` / `->list()` / `->abort($id)` / `->wait($id)` | `/v1/requests` |
| `$client->account->get()` / `->usage([...])` / `->operations()` | `/v1/account`, `/v1/usage`, `/v1/operations` |
| `$client->files->get($id)` / `->delete($id)` | `/v1/files` |
| `$client->uploads->create($path)` / `->get($id)` / `->delete($id)` | `/v1/uploads` |
| `$client->events->since($time, $limit)` / `$client->fonts->list()` | `/v1/events`, `/v1/fonts` |
| `$client->storage->connect([...])` / `->list()` / `->get($id)` / `->update($id, ['prefixes' => [...]])` / `->verify($id)` / `->disconnect($id)` | `/v1/storage` |

Operations and their parameters: https://api.watermark.ws/docs/operations.md

## Errors

API errors throw `Watermark\Api\Exceptions\ApiException` subclasses (`InvalidRequestException`, `PaymentRequiredException`, `RateLimitException`, ...). Branch on `getErrorCode()`; `getParam()` names the input at fault. The client passes every error code through as the API sends it, so a code added later needs no update; the codes are listed at https://api.watermark.ws/docs/errors.md. `getError()` is the whole error object, with the fields some errors add, such as the `permissions_policy` a storage change whose check failed needs. A failed *request* is not an exception: its `status` is `failed` and `error` says why; nor is a `partial` batch, whose failed files `$client->batches->requests($id, ['status' => 'failed'])` lists.

Batches and templates add these codes:

- `batch_too_large` (422, `InvalidRequestException`): more files than the account's `batch_max_files`, a listed prefix included.
- `empty_source_list` (422, `InvalidRequestException`): the sources named no file.
- `batch_not_found` (404, `NotFoundException`): no such batch on the account.
- `batch_not_finished` (409, `ConflictException`): a retry of a batch that is still running.
- `nothing_to_retry` (409, `ConflictException`): a retry of a batch with no failed file.
- `batch_finished` (409, `ConflictException`): an abort of a batch that is already done.
- `template_not_found` (404, `NotFoundException`): no such template on the account, or it was deleted.
- `template_media_mismatch` (422, `InvalidRequestException`): a template made for another media.
- `too_many_templates` (422, `InvalidRequestException`): the account already keeps `max_templates`.
- `upload_in_use` (409, `ConflictException`): an upload a request or template has already taken.

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

`tests/SpecCoverageTest.php` holds the client to the API's OpenAPI spec: every endpoint has a method, every parameter is sent, and the spec is the one in `spec/openapi.json`, whose sha256 is in `spec.sha`. It reads the spec from `WATERMARK_API_SPEC` (a path or URL), else `../watermark-api`, else `spec/openapi.json`. When the API changes, update the library, then run `composer spec:refresh` to record the new spec as checked.
