<?php

namespace Watermark\Api\Resources;

use Watermark\Api\Transport;

/**
 * Designs: named operations lists kept on the account, with the logo they
 * use, to send as "design" on a processing call, a batch or a preview.
 * Making, changing and deleting them needs the "designs" scope on the key;
 * using one needs only "process".
 *
 *     $design = $client->designs->create([
 *         'name'       => 'Studio corner logo',
 *         'operations' => [['type' => 'watermark', 'image' => 'upl_…', 'anchor' => 'bottom_right', 'margin' => '3%', 'size' => '18%']],
 *     ]);
 *     $client->images->process($photo, [], ['design' => $design['id']]);
 */
class Designs
{
    public function __construct(private Transport $transport)
    {
    }

    /**
     * @param array<string, mixed> $design name, operations, and media (image, video, document or any; any unless sent).
     *                                     A watermark whose image is an upload or a file becomes an asset of the design.
     *
     * @return array<string, mixed> The design, its id dsn_….
     */
    public function create(array $design):array
    {
        return $this->transport->request('POST', '/v1/designs', $design);
    }

    /**
     * The account's designs, newest first.
     *
     * @param array<string, mixed> $query limit (1-100), before (a dsn_… id, for the next page).
     *
     * @return array<string, mixed> {"data": [...], "has_more": bool}
     */
    public function list(array $query = []):array
    {
        return $this->transport->request('GET', '/v1/designs', null, $query);
    }

    /**
     * @param string $id dsn_…
     *
     * @return array<string, mixed>
     */
    public function get(string $id):array
    {
        return $this->transport->request('GET', '/v1/designs/' . rawurlencode($id));
    }

    /**
     * Change the name, the media or the operations, whichever are sent.
     *
     * @param string               $id      dsn_…
     * @param array<string, mixed> $changes name, media, operations.
     *
     * @return array<string, mixed>
     */
    public function update(string $id, array $changes):array
    {
        return $this->transport->request('PATCH', '/v1/designs/' . rawurlencode($id), $changes);
    }

    /**
     * Delete a design and its assets.
     *
     * @param string $id dsn_…
     *
     * @return array<string, mixed> The design, with deleted_at.
     */
    public function delete(string $id):array
    {
        return $this->transport->request('DELETE', '/v1/designs/' . rawurlencode($id));
    }
}
