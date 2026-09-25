<?php

declare(strict_types=1);

namespace Duta\Resources;

use Duta\HttpClient;

final class ApiKeys
{
    /** @internal */
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * The key is in 'token' and is shown only once.
     *
     * @param array{name: string, permission?: 'full_access'|'sending_access'} $key
     * @return array<string, mixed>
     */
    public function create(array $key): array
    {
        return $this->http->request('POST', '/api-keys', body: $key);
    }

    /** @return array{object: string, has_more: bool, data: list<array<string, mixed>>} */
    public function list(): array
    {
        /** @var array{object: string, has_more: bool, data: list<array<string, mixed>>} */
        return $this->http->request('GET', '/api-keys', idempotent: true);
    }

    /** @return array<string, mixed> */
    public function remove(string $id): array
    {
        return $this->http->request('DELETE', '/api-keys/' . rawurlencode($id), idempotent: true);
    }
}
