<?php

namespace Watermark\Api\Tests;

use PHPUnit\Framework\TestCase;
use Watermark\Api\Exceptions\WebhookSignatureException;
use Watermark\Api\Webhook;

class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_test';

    public function test_a_signed_delivery_is_read():void
    {
        $body = '{"id":"evt_1_done","type":"request.succeeded","data":{"id":1}}';

        $event = Webhook::constructEvent($body, $this->sign($body, 1000), self::SECRET, 300, 1100);

        $this->assertSame('request.succeeded', $event['type']);
    }

    public function test_a_changed_body_a_wrong_secret_or_an_old_delivery_is_refused():void
    {
        $body = '{"id":"evt_1_done"}';

        foreach ([
            [$body . ' ', $this->sign($body, 1000), self::SECRET, 1000],
            [$body, $this->sign($body, 1000), 'whsec_other', 1000],
            [$body, $this->sign($body, 1000), self::SECRET, 1000 + 301],
            [$body, 'v1=abc', self::SECRET, 1000],
        ] as [$received, $header, $secret, $now]) {
            try {
                Webhook::constructEvent($received, $header, $secret, 300, $now);
                $this->fail('A bad delivery was accepted.');
            } catch (WebhookSignatureException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function sign(string $body, int $timestamp):string
    {
        return "t={$timestamp},v1=" . hash_hmac('sha256', "{$timestamp}.{$body}", self::SECRET);
    }
}
