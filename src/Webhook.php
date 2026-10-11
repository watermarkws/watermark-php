<?php

namespace Watermark\Api;

use Watermark\Api\Exceptions\WebhookSignatureException;

/**
 * Checks a webhook came from the API, then reads it.
 *
 *     $event = Webhook::constructEvent(file_get_contents('php://input'), $_SERVER['HTTP_WATERMARK_SIGNATURE'] ?? '', $secret);
 *     if ($event['type'] === 'request.succeeded') { ... $event['data']['result'] ... }
 */
final class Webhook
{
    /**
     * Verify the Watermark-Signature header and return the event.
     *
     * @param string   $body      The raw request body, exactly as received.
     * @param string   $header    The Watermark-Signature header.
     * @param string   $secret    The account's webhook secret (whsec_…).
     * @param int      $tolerance Seconds a delivery may be old.
     * @param int|null $now       The time to check against; now when null.
     *
     * @return array<string, mixed>
     * @throws WebhookSignatureException
     */
    public static function constructEvent(string $body, string $header, string $secret, int $tolerance = 300, ?int $now = null):array
    {
        if (! preg_match('/^t=(\d+),v1=([0-9a-f]{64})$/', trim($header), $match)) {
            throw new WebhookSignatureException('The Watermark-Signature header is missing or malformed.');
        }

        [, $timestamp, $signature] = $match;

        if (! hash_equals(hash_hmac('sha256', "{$timestamp}.{$body}", $secret), $signature)) {
            throw new WebhookSignatureException('The signature does not match: check the webhook secret, and that the body is unchanged.');
        }

        if (abs(($now ?? time()) - (int) $timestamp) > $tolerance) {
            throw new WebhookSignatureException('The delivery is too old; it may be a replay.');
        }

        $event = json_decode($body, true);

        if (! is_array($event)) {
            throw new WebhookSignatureException('The body is not JSON.');
        }

        return $event;
    }
}
