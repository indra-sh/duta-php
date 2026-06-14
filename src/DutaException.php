<?php

declare(strict_types=1);

namespace Duta;

/**
 * Thrown when the Duta API returns an error.
 */
class DutaException extends \Exception
{
    /** @var string Machine-readable error name, e.g. "rate_limit_exceeded". */
    public string $name;

    /** @var int HTTP status code (0 for network errors). */
    public int $statusCode;

    /** @var string[]|null Suppressed recipient addresses, present on some 422 errors. */
    public ?array $blocked;

    private const NAME_BY_STATUS = [
        400 => 'validation_error',
        401 => 'authentication_error',
        403 => 'permission_denied',
        404 => 'not_found',
        422 => 'unprocessable_entity',
        429 => 'rate_limit_exceeded',
        500 => 'internal_server_error',
    ];

    public function __construct(string $name, string $message, int $statusCode, ?array $blocked = null)
    {
        parent::__construct($message, $statusCode);
        $this->name = $name;
        $this->statusCode = $statusCode;
        $this->blocked = $blocked;
    }

    /**
     * Normalise either of Duta's error shapes into a DutaException.
     *
     * @param mixed $body
     */
    public static function fromResponse(int $statusCode, $body): self
    {
        $fallback = self::NAME_BY_STATUS[$statusCode] ?? 'api_error';
        if (is_array($body)) {
            $blocked = isset($body['blocked']) && is_array($body['blocked']) ? $body['blocked'] : null;
            // Rate-limit shape: { statusCode, name, message }
            if (isset($body['name'], $body['message']) && is_string($body['name']) && is_string($body['message'])) {
                return new self($body['name'], $body['message'], $statusCode, $blocked);
            }
            // Common shape: { error: string }
            if (isset($body['error']) && is_string($body['error'])) {
                return new self($fallback, $body['error'], $statusCode, $blocked);
            }
        }
        return new self($fallback, "Request failed with status {$statusCode}", $statusCode);
    }
}
