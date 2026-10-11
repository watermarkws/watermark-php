<?php

namespace Watermark\Api\Exceptions;

use RuntimeException;
use Throwable;

/**
 * An error the API answered with: {"error": {"type", "code", "message", "param"?, "request_id"?}}.
 * Branch on getErrorCode(); show getMessage() to people.
 */
class ApiException extends RuntimeException
{
    /**
     * @param string                     $message
     * @param int                        $status    The HTTP status.
     * @param string                     $type      Such as "invalid_request".
     * @param string                     $errorCode Such as "insufficient_credits".
     * @param string|null                $param     The input the error is about.
     * @param string|null                $requestId
     * @param array<string, string[]>    $errors    Every invalid field, for a validation error.
     * @param Throwable|null             $previous
     */
    public function __construct(
        string $message,
        private int $status,
        private string $type,
        private string $errorCode,
        private ?string $param = null,
        private ?string $requestId = null,
        private array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    /**
     * Build the exception for an error body, as the subclass its type calls for.
     *
     * @param int                  $status
     * @param array<string, mixed> $body
     *
     * @return static
     */
    public static function fromResponse(int $status, array $body):self
    {
        $error = $body['error'] ?? [];

        $class = match ($error['type'] ?? null) {
            'authentication'   => AuthenticationException::class,
            'permission'       => PermissionException::class,
            'payment_required' => PaymentRequiredException::class,
            'not_found'        => NotFoundException::class,
            'conflict'         => ConflictException::class,
            'rate_limited'     => RateLimitException::class,
            'invalid_request'  => InvalidRequestException::class,
            default            => self::class,
        };

        return new $class(
            $error['message'] ?? "The API answered {$status}.",
            $status,
            $error['type'] ?? 'server_error',
            $error['code'] ?? 'unknown',
            $error['param'] ?? null,
            isset($error['request_id']) ? (string) $error['request_id'] : null,
            $error['errors'] ?? [],
        );
    }

    public function getStatus():int
    {
        return $this->status;
    }

    public function getType():string
    {
        return $this->type;
    }

    public function getErrorCode():string
    {
        return $this->errorCode;
    }

    public function getParam():?string
    {
        return $this->param;
    }

    public function getRequestId():?string
    {
        return $this->requestId;
    }

    /**
     * @return array<string, string[]>
     */
    public function getErrors():array
    {
        return $this->errors;
    }
}
