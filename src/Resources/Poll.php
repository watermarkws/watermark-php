<?php

namespace Watermark\Api\Resources;

use Watermark\Api\Exceptions\TimeoutException;

/**
 * The long-poll loop requests and batches wait with: each call asks the API
 * to hold the response until the object finishes, up to 25 seconds, so a
 * result comes back as soon as it is ready.
 *
 * @internal
 */
final class Poll
{
    /**
     * The longest the API holds a response, in seconds.
     */
    public const MAX_HOLD = 25;

    /**
     * @param \Closure(int|null): array<string, mixed> $fetch    Gets the object, holding for the seconds given (null: at once).
     * @param string[]                                 $finished The statuses to stop at.
     * @param string                                   $label    Such as "Request 1234", for the timeout's message.
     * @param float                                    $timeout  Seconds to wait in all.
     * @param float                                    $interval Seconds between calls when the API answers at once; grows to 5.
     * @param \Closure(float): void                    $sleep
     *
     * @return array<string, mixed>
     * @throws TimeoutException
     */
    public static function until(\Closure $fetch, array $finished, string $label, float $timeout, float $interval, \Closure $sleep):array
    {
        $waited = 0.0;

        while (true) {
            $hold = (int) min(self::MAX_HOLD, max(0, $timeout - $waited));
            $before = microtime(true);
            $current = $fetch($hold ?: null);
            $took = microtime(true) - $before;
            $waited += $took;

            if (in_array($current['status'] ?? null, $finished, true)) {
                return $current;
            }

            if ($waited >= $timeout) {
                throw new TimeoutException("{$label} was still {$current['status']} after {$timeout} seconds.");
            }

            if ($hold > 0 && $took >= $hold - 1) {
                continue;
            }

            $sleep($interval);
            $waited += $interval;
            $interval = min(5.0, $interval * 1.5);
        }
    }
}
