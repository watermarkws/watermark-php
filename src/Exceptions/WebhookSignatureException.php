<?php

namespace Watermark\Api\Exceptions;

use RuntimeException;

/**
 * A webhook whose Watermark-Signature does not match, or is too old.
 */
class WebhookSignatureException extends RuntimeException
{
}
