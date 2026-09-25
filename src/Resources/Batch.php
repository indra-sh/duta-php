<?php

declare(strict_types=1);

namespace Duta\Resources;

use Duta\HttpClient;

final class Batch
{
    /** @internal */
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * Send up to 100 emails. Strict by default: one invalid email and none are
     * sent. 'validation' => 'permissive' sends the valid ones.
     *
     * @param list<array<string, mixed>> $emails
     * @param array{idempotency_key?: string, validation?: 'strict'|'permissive'} $options
     * @return array{data: list<array{id: string, status: string}>, errors?: list<array<string, mixed>>}
     */
    public function send(array $emails, array $options = []): array
    {
        /** @var array{data: list<array{id: string, status: string}>, errors?: list<array<string, mixed>>} */
        return $this->http->request('POST', '/emails/batch', body: $emails, headers: [
            'Idempotency-Key' => $options['idempotency_key'] ?? Keys::idempotency(),
            'x-batch-validation' => $options['validation'] ?? null,
        ], idempotent: true);
    }
}
