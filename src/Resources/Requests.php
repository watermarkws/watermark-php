<?php

namespace Watermark\Api\Resources;

use Watermark\Api\Exceptions\TimeoutException;
use Watermark\Api\Transport;

/**
 * Follow, list and abort requests.
 */
class Requests
{
    /**
     * The statuses a request ends in.
     */
    public const FINISHED = ['succeeded', 'failed', 'aborted'];

    /**
     * The longest the API holds a response, in seconds.
     */
    public const MAX_HOLD = Poll::MAX_HOLD;

    /**
     * @param Transport              $transport
     * @param \Closure(float): void  $sleep
     */
    public function __construct(private Transport $transport, private \Closure $sleep)
    {
    }

    /**
     * @param int      $id
     * @param int|null $wait Seconds, up to 25, for the API to hold the response until the request finishes.
     *
     * @return array<string, mixed>
     */
    public function get(int $id, ?int $wait = null):array
    {
        return $this->transport->request('GET', "/v1/requests/{$id}", null, ['wait' => $wait]);
    }

    /**
     * The account's requests, newest first.
     *
     * @param int      $limit
     * @param int|null $before Only older than this id, for the next page.
     *
     * @return array<string, mixed> {"data": [...], "has_more": bool}
     */
    public function list(int $limit = 20, ?int $before = null):array
    {
        return $this->transport->request('GET', '/v1/requests', null, ['limit' => $limit, 'before' => $before]);
    }

    /**
     * @param int $id
     *
     * @return array<string, mixed>
     */
    public function abort(int $id):array
    {
        return $this->transport->request('POST', "/v1/requests/{$id}/abort");
    }

    /**
     * Wait for a request to finish, and return it. Each call asks the API to
     * hold the response until the request finishes (long polling), so a
     * result comes back as soon as it is ready.
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
        return Poll::until(fn (?int $hold) => $this->get($id, $hold), self::FINISHED, "Request {$id}", $timeout, $interval, $this->sleep);
    }
}
