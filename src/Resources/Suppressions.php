<?php

declare(strict_types=1);

namespace Duta\Resources;

use Duta\HttpClient;

final class Suppressions
{
    /** @internal */
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * @param array{limit?: int, after?: string, q?: string} $query
     * @return array{data: list<array<string, mixed>>, has_more: bool, next: ?string}
     */
    public function list(array $query = []): array
    {
        /** @var array{data: list<array<string, mixed>>, has_more: bool, next: ?string} */
        return $this->http->request('GET', '/suppressions', $query, idempotent: true);
    }

    /**
     * @param array{limit?: int, q?: string} $query
     * @return \Generator<int, array<string, mixed>>
     */
    public function listAll(array $query = []): \Generator
    {
        return Pages::all(fn (?string $after) => $this->list([...$query, 'after' => $after]));
    }

    /** @return array<string, mixed> */
    public function create(string $email): array
    {
        return $this->http->request('POST', '/suppressions', body: ['email' => $email], idempotent: true);
    }

    /**
     * Only manual entries can be removed. Bounces, complaints and unsubscribes are permanent.
     *
     * @return array<string, mixed>
     */
    public function remove(string $email): array
    {
        return $this->http->request('DELETE', '/suppressions/' . rawurlencode($email), idempotent: true);
    }
}
