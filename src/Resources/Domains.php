<?php

declare(strict_types=1);

namespace Duta\Resources;

use Duta\HttpClient;

final class Domains
{
    /** @internal */
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * @param array{name: string} $domain
     * @return array<string, mixed> the domain with the DNS records to publish
     */
    public function create(array $domain): array
    {
        return $this->http->request('POST', '/domains', body: $domain);
    }

    /** @return array{object: string, has_more: bool, data: list<array<string, mixed>>} */
    public function list(): array
    {
        /** @var array{object: string, has_more: bool, data: list<array<string, mixed>>} */
        return $this->http->request('GET', '/domains', idempotent: true);
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/domains/' . rawurlencode($id), idempotent: true);
    }

    /**
     * Check the domain's DNS now rather than waiting for Duta's own check.
     *
     * @return array<string, mixed>
     */
    public function verify(string $id): array
    {
        return $this->http->request('POST', '/domains/' . rawurlencode($id) . '/verify', idempotent: true);
    }

    /** @return array<string, mixed> */
    public function remove(string $id): array
    {
        return $this->http->request('DELETE', '/domains/' . rawurlencode($id), idempotent: true);
    }
}
