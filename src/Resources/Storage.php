<?php

namespace Watermark\Api\Resources;

use Watermark\Api\Transport;

/**
 * Connections to your own S3 bucket, which requests can read sources from and
 * write outputs to as {"storage": "stg_…", "key": "…"}.
 */
class Storage
{
    public function __construct(private Transport $transport)
    {
    }

    /**
     * @return array<string, mixed> {"data": [...]}
     */
    public function list():array
    {
        return $this->transport->request('GET', '/v1/storage');
    }

    /**
     * Connect a bucket. The connection starts unverified: create the role
     * with the trust and permissions policies it returns, then verify it.
     *
     * @param array<string, mixed> $connection bucket, region, role_arn, prefixes.
     *
     * @return array<string, mixed>
     */
    public function connect(array $connection):array
    {
        return $this->transport->request('POST', '/v1/storage', $connection);
    }

    /**
     * @param string $id stg_…
     *
     * @return array<string, mixed>
     */
    public function get(string $id):array
    {
        return $this->transport->request('GET', '/v1/storage/' . rawurlencode($id));
    }

    /**
     * Check the API can assume the role and read and write the bucket.
     *
     * @param string $id stg_…
     *
     * @return array<string, mixed>
     */
    public function verify(string $id):array
    {
        return $this->transport->request('POST', '/v1/storage/' . rawurlencode($id) . '/verify');
    }

    /**
     * @param string $id stg_…
     *
     * @return array<string, mixed>
     */
    public function disconnect(string $id):array
    {
        return $this->transport->request('DELETE', '/v1/storage/' . rawurlencode($id));
    }
}
