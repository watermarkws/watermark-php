<?php

namespace Watermark\Api\Resources;

use Watermark\Api\Transport;

/**
 * The account behind the key, its usage, and the operation catalog.
 */
class Account
{
    public function __construct(private Transport $transport)
    {
    }

    /**
     * Balance, tier, and the key's mode and limits.
     *
     * @return array<string, mixed>
     */
    public function get():array
    {
        return $this->transport->request('GET', '/v1/account');
    }

    /**
     * @param array<string, mixed> $query from, to (Y-m-d), group_by (day, endpoint, customer, status), customer.
     *
     * @return array<string, mixed>
     */
    public function usage(array $query = []):array
    {
        return $this->transport->request('GET', '/v1/usage', null, $query);
    }

    /**
     * Every operation and its parameters, per media type.
     *
     * @return array<string, mixed>
     */
    public function operations():array
    {
        return $this->transport->request('GET', '/v1/operations');
    }
}
