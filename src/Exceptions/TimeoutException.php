<?php

namespace Watermark\Api\Exceptions;

use RuntimeException;

/**
 * A request that had not finished when waiting for it gave up.
 */
class TimeoutException extends RuntimeException
{
}
