<?php

declare(strict_types=1);

namespace Duta;

/**
 * An error from the Duta API, or from reaching it. `getErrorCode()` is Duta's
 * code (see https://docs.duta.indra.sh/guides/errors/), `getName()` the
 * Resend-compatible name and `getRequestId()` finds the request on the Logs
 * screen.
 */
class DutaException extends \RuntimeException
{
    /** @param array<string, mixed>|null $detail */
    public function __construct(
        string $message,
        private readonly ?int $statusCode,
        private readonly string $errorName,
        private readonly string $errorCode,
        private readonly ?string $requestId,
        private readonly ?array $detail = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }

    /** Null when Duta could not be reached at all. */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getName(): string
    {
        return $this->errorName;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /** @return array<string, mixed>|null */
    public function getDetail(): ?array
    {
        return $this->detail;
    }
}
