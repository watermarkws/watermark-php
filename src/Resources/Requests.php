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
    public const MAX_HOLD = 25;

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
        $waited = 0.0;

        while (true) {
            $hold = (int) min(self::MAX_HOLD, max(0, $timeout - $waited));
            $before = microtime(true);
            $request = $this->get($id, $hold ?: null);
            $took = microtime(true) - $before;
            $waited += $took;

            if (in_array($request['status'] ?? null, self::FINISHED, true)) {
                return $request;
            }

            if ($waited >= $timeout) {
                throw new TimeoutException("Request {$id} was still {$request['status']} after {$timeout} seconds.");
            }

            if ($hold > 0 && $took >= $hold - 1) {
                continue;
            }

            ($this->sleep)($interval);
            $waited += $interval;
            $interval = min(5.0, $interval * 1.5);
        }
    }
}
