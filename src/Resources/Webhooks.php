<?php

declare(strict_types=1);

namespace Duta\Resources;

use Duta\HttpClient;
use Duta\Webhook;

final class Webhooks
{
    /** @internal */
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * The 'signing_secret' in the answer is shown only once.
     *
     * @param array{endpoint: string, events?: list<string>} $webhook
     * @return array<string, mixed>
     */
    public function create(array $webhook): array
    {
        return $this->http->request('POST', '/webhooks', body: $webhook);
    }

    /** @return array{object: string, has_more: bool, data: list<array<string, mixed>>} */
    public function list(): array
    {
        /** @var array{object: string, has_more: bool, data: list<array<string, mixed>>} */
        return $this->http->request('GET', '/webhooks', idempotent: true);
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/webhooks/' . rawurlencode($id), idempotent: true);
    }

    /** @return array<string, mixed> */
    public function remove(string $id): array
    {
        return $this->http->request('DELETE', '/webhooks/' . rawurlencode($id), idempotent: true);
    }

    /**
     * Enable an endpoint that failed its way to disabled.
     *
     * @return array<string, mixed>
     */
    public function enable(string $id): array
    {
        return $this->http->request('POST', '/webhooks/' . rawurlencode($id) . '/enable', idempotent: true);
    }

    /**
     * Send a signed test event to the endpoint.
     *
     * @return array<string, mixed>
     */
    public function test(string $id): array
    {
        return $this->http->request('POST', '/webhooks/' . rawurlencode($id) . '/test');
    }

    /**
     * @param array{limit?: int, after?: string} $query
     * @return array{data: list<array<string, mixed>>, has_more: bool, next: ?string}
     */
    public function deliveries(string $id, array $query = []): array
    {
        /** @var array{data: list<array<string, mixed>>, has_more: bool, next: ?string} */
        return $this->http->request('GET', '/webhooks/' . rawurlencode($id) . '/deliveries', $query, idempotent: true);
    }

    /**
     * Check a delivery's signature and return the event. Throws
     * WebhookVerificationException when it is not from Duta or is too old.
     *
     * @param array<string, string|list<string>> $headers
     * @return array<string, mixed>
     */
    public function verify(string $payload, array $headers, string $secret, int $toleranceSeconds = 300): array
    {
        return Webhook::verify($payload, $headers, $secret, $toleranceSeconds);
    }
}
