<?php

namespace Watermark\Api\Resources;

use Watermark\Api\Exceptions\TimeoutException;
use Watermark\Api\Transport;

/**
 * The same operations on many files in one call. Each file becomes an
 * ordinary request (with a "batch" and a "position"); the batch zips the
 * results at the end or writes them into your bucket.
 *
 *     $batch = $client->batches->create($urls, [
 *         'design' => 'dsn_…',
 *         'output' => ['format' => 'webp'],
 *         'zip'    => true,
 *     ]);
 *     $done = $client->batches->wait($batch['id']);
 *     $zips = $done['zips'];
 */
class Batches
{
    /**
     * The statuses a batch ends in.
     */
    public const FINISHED = ['succeeded', 'partial', 'failed', 'aborted'];

    /**
     * @param Transport             $transport
     * @param \Closure(float): void $sleep
     */
    public function __construct(private Transport $transport, private \Closure $sleep)
    {
    }

    /**
     * Start a batch. The API checks every source and prices the whole batch
     * before answering.
     *
     * @param array<int, string|array<string, string>> $sources Each a URL, upl_…, file_…, {"storage": "stg_…", "key": "…"}
     *                                                          or {"storage": "stg_…", "prefix": "folder/"}.
     * @param array<string, mixed>                     $options media, design, operations (one of design and operations is required),
     *                                                          output or outputs, destination, zip, max_duration, max_pages,
     *                                                          webhook_url, events, request_events, customer, metadata, idempotency_key.
     *
     * @return array<string, mixed> The batch, status queued.
     */
    public function create(array $sources, array $options = []):array
    {
        return Endpoint::post($this->transport, '/v1/batches', ['sources' => $sources], $options);
    }

    /**
     * The account's batches, newest first.
     *
     * @param array<string, mixed> $query limit (1-100), before (an id, for the next page), status.
     *
     * @return array<string, mixed> {"data": [...], "has_more": bool}
     */
    public function list(array $query = []):array
    {
        return $this->transport->request('GET', '/v1/batches', null, $query);
    }

    /**
     * @param int      $id
     * @param int|null $wait Seconds, up to 25, for the API to hold the response until the batch finishes.
     *
     * @return array<string, mixed>
     */
    public function get(int $id, ?int $wait = null):array
    {
        return $this->transport->request('GET', "/v1/batches/{$id}", null, ['wait' => $wait]);
    }

    /**
     * The batch's files as requests, in position order.
     *
     * @param int                  $id
     * @param array<string, mixed> $query status (such as failed), after (a position), limit (1-100).
     *
     * @return array<string, mixed> {"data": [...], "has_more": bool}
     */
    public function requests(int $id, array $query = []):array
    {
        return $this->transport->request('GET', "/v1/batches/{$id}/requests", null, $query);
    }

    /**
     * Run the failed files of a finished batch again.
     *
     * @param int $id
     *
     * @return array<string, mixed>
     */
    public function retry(int $id):array
    {
        return $this->transport->request('POST', "/v1/batches/{$id}/retry");
    }

    /**
     * Stop a batch: waiting files never run, running ones are aborted.
     *
     * @param int $id
     *
     * @return array<string, mixed>
     */
    public function abort(int $id):array
    {
        return $this->transport->request('POST', "/v1/batches/{$id}/abort");
    }

    /**
     * Wait for a batch to finish (succeeded, partial, failed or aborted),
     * and return it. Each call asks the API to hold the response until the
     * batch finishes (long polling).
     *
     * @param int   $id
     * @param float $timeout  Seconds to wait in all.
     * @param float $interval Seconds between calls when the API answers at once; grows to 5.
     *
     * @return array<string, mixed>
     * @throws TimeoutException
     */
    public function wait(int $id, float $timeout = 300, float $interval = 1):array
    {
        return Poll::until(fn (?int $hold) => $this->get($id, $hold), self::FINISHED, "Batch {$id}", $timeout, $interval, $this->sleep);
    }
}
