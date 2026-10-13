<?php

namespace Watermark\Api\Resources;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Watermark\Api\Exceptions\ApiException;
use Watermark\Api\Transport;

/**
 * Send a local file to the API, then pass the upload's id as a request's
 * source. An upload is deleted once a request has used it.
 *
 *     $upload = $client->uploads->create('IMG_4471.heic');
 *     $request = $client->images->process($upload['id'], [...]);
 */
class Uploads
{
    private ClientInterface $http;

    /**
     * @param Transport            $transport
     * @param ClientInterface|null $http For the PUT of the file itself.
     */
    public function __construct(private Transport $transport, ?ClientInterface $http = null)
    {
        $this->http = $http ?? new Guzzle();
    }

    /**
     * Make an upload for a local file and PUT the file to it.
     *
     * @param string      $path
     * @param string|null $contentType Guessed from the file when left out.
     *
     * @return array<string, mixed> The upload, its id to use as a source.
     * @throws ApiException
     */
    public function create(string $path, ?string $contentType = null):array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new \InvalidArgumentException("Cannot read {$path}.");
        }

        $contentType ??= self::contentTypeOf($path);

        $upload = $this->transport->request('POST', '/v1/uploads', [
            'filename'     => basename($path),
            'size'         => filesize($path),
            'content_type' => $contentType,
        ]);

        $response = $this->http->request('PUT', $upload['upload']['url'], [
            RequestOptions::HEADERS     => $upload['upload']['headers'] ?? ['Content-Type' => $contentType],
            RequestOptions::BODY        => Utils::streamFor(Utils::tryFopen($path, 'rb')),
            RequestOptions::HTTP_ERRORS => false,
        ]);

        if ($response->getStatusCode() >= 300) {
            throw new ApiException("The file could not be sent ({$response->getStatusCode()}).", $response->getStatusCode());
        }

        return $upload;
    }

    /**
     * @param string $id upl_…
     *
     * @return array<string, mixed>
     */
    public function get(string $id):array
    {
        return $this->transport->request('GET', '/v1/uploads/' . rawurlencode($id));
    }

    /**
     * Delete an upload no request has used.
     *
     * @param string $id upl_…
     *
     * @return array<string, mixed>
     */
    public function delete(string $id):array
    {
        return $this->transport->request('DELETE', '/v1/uploads/' . rawurlencode($id));
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private static function contentTypeOf(string $path):string
    {
        $byExtension = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
            'heic' => 'image/heic', 'heif' => 'image/heif', 'tif' => 'image/tiff', 'tiff' => 'image/tiff', 'avif' => 'image/avif',
            'mp4' => 'video/mp4', 'mov' => 'video/quicktime', 'm4v' => 'video/x-m4v', 'webm' => 'video/webm', 'mkv' => 'video/x-matroska',
            'pdf' => 'application/pdf', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        $type = $byExtension[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;

        if ($type === null && function_exists('mime_content_type')) {
            $type = mime_content_type($path) ?: null;
        }

        return $type ?? 'application/octet-stream';
    }
}
