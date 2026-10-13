<?php

namespace Watermark\Api\Resources;

use Watermark\Api\Transport;

/**
 * Files a request kept (outputs with a managed destination): look one up for
 * a fresh download link, or delete it before its retention ends.
 */
class Files
{
    public function __construct(private Transport $transport)
    {
    }

    /**
     * @param string $id file_…
     *
     * @return array<string, mixed> The file, with its download url.
     */
    public function get(string $id):array
    {
        return $this->transport->request('GET', '/v1/files/' . rawurlencode($id));
    }

    /**
     * @param string $id file_…
     *
     * @return array<string, mixed> The file, with deleted_at.
     */
    public function delete(string $id):array
    {
        return $this->transport->request('DELETE', '/v1/files/' . rawurlencode($id));
    }
}
