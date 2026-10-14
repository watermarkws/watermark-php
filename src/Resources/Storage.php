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
     * @param array<string, mixed> $connection bucket, region, role_arn, prefixes, and read and write
     *                                         (both true unless sent); provider is s3 unless sent.
     *
     * @return array<string, mixed>
     */
    public function connect(array $connection):array
    {
        return $this->transport->request('POST', '/v1/storage', $connection + ['provider' => 's3']);
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
     * Change the prefixes, or whether the connection may read or write. Send
     * the whole new list of prefixes. The API checks the new settings before
     * keeping them; a verified connection whose check fails stays as it was,
     * and the error carries the permissions_policy the new settings need.
     *
     * @param string               $id      stg_…
     * @param array<string, mixed> $changes prefixes, read, write.
     *
     * @return array<string, mixed>
     */
    public function update(string $id, array $changes):array
    {
        return $this->transport->request('PATCH', '/v1/storage/' . rawurlencode($id), $changes);
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
