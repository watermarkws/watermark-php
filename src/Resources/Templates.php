<?php

namespace Watermark\Api\Resources;

use Watermark\Api\Transport;

/**
 * Templates: named operations lists kept on the account, with the logo they
 * use, to send as "template" on a processing call, a batch or a preview.
 * Making, changing and deleting them needs the "templates" scope on the key;
 * using one needs only "process".
 *
 *     $template = $client->templates->create([
 *         'name'       => 'Studio corner logo',
 *         'operations' => [['type' => 'watermark', 'image' => 'upl_…', 'anchor' => 'bottom_right', 'margin' => '3%', 'size' => '18%']],
 *     ]);
 *     $client->images->process($photo, [], ['template' => $template['id']]);
 */
class Templates
{
    public function __construct(private Transport $transport)
    {
    }

    /**
     * @param array<string, mixed> $template name, operations, and media (image, video, document or any; any unless sent).
     *                                     A watermark whose image is an upload or a file becomes an asset of the template.
     *
     * @return array<string, mixed> The template, its id tpl_….
     */
    public function create(array $template):array
    {
        return $this->transport->request('POST', '/v1/templates', $template);
    }

    /**
     * The account's templates, newest first.
     *
     * @param array<string, mixed> $query limit (1-100), before (a tpl_… id, for the next page).
     *
     * @return array<string, mixed> {"data": [...], "has_more": bool}
     */
    public function list(array $query = []):array
    {
        return $this->transport->request('GET', '/v1/templates', null, $query);
    }

    /**
     * @param string $id tpl_…
     *
     * @return array<string, mixed>
     */
    public function get(string $id):array
    {
        return $this->transport->request('GET', '/v1/templates/' . rawurlencode($id));
    }

    /**
     * Change the name, the media or the operations, whichever are sent.
     *
     * @param string               $id      tpl_…
     * @param array<string, mixed> $changes name, media, operations.
     *
     * @return array<string, mixed>
     */
    public function update(string $id, array $changes):array
    {
        return $this->transport->request('PATCH', '/v1/templates/' . rawurlencode($id), $changes);
    }

    /**
     * Delete a template and its assets.
     *
     * @param string $id tpl_…
     *
     * @return array<string, mixed> The template, with deleted_at.
     */
    public function delete(string $id):array
    {
        return $this->transport->request('DELETE', '/v1/templates/' . rawurlencode($id));
    }
}
