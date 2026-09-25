<?php

declare(strict_types=1);

namespace Duta\Resources;

use Duta\HttpClient;

final class Logs
{
    /** @internal */
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * @param array{limit?: int, after?: string, q?: string, status?: string, method?: string} $query
     * @return array{data: list<array<string, mixed>>, has_more: bool, next: ?string}
     */
    public function list(array $query = []): array
    {
        /** @var array{data: list<array<string, mixed>>, has_more: bool, next: ?string} */
        return $this->http->request('GET', '/logs', $query, idempotent: true);
    }

    /**
     * @param array{limit?: int, q?: string, status?: string, method?: string} $query
     * @return \Generator<int, array<string, mixed>>
     */
    public function listAll(array $query = []): \Generator
    {
        return Pages::all(fn (?string $after) => $this->list([...$query, 'after' => $after]));
    }

    /**
     * A request id from an error or the X-Request-Id header.
     *
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/logs/' . rawurlencode($id), idempotent: true);
    }
}
