<?php

namespace Watermark\Api\Resources;

use Watermark\Api\Transport;

/**
 * The built-in fonts the text operation takes by name.
 */
class Fonts
{
    public function __construct(private Transport $transport)
    {
    }

    /**
     * @return array<string, mixed> {"data": [{"name": "inter-bold", ...}]}
     */
    public function list():array
    {
        return $this->transport->request('GET', '/v1/fonts');
    }
}
