<?php

namespace Watermark\Api\Resources;

use Watermark\Api\Transport;

/**
 * The webhook events of the last 30 days, for catching up after a receiver
 * was down.
 */
class Events
{
    public function __construct(private Transport $transport)
    {
    }

    /**
     * The events after a moment, oldest first.
     *
     * @param string|\DateTimeInterface $since
     * @param int                       $limit Up to 100; with has_more, ask again from the last one's created.
     *
     * @return array<string, mixed> {"data": [...], "has_more": bool}
     */
    public function since(string|\DateTimeInterface $since, int $limit = 100):array
    {
        if ($since instanceof \DateTimeInterface) {
            $since = $since->format(\DateTimeInterface::ATOM);
        }

        return $this->transport->request('GET', '/v1/events', null, ['since' => $since, 'limit' => $limit]);
    }
}
