<?php

declare(strict_types=1);

namespace Duta\Resources;

use Duta\HttpClient;

final class Emails
{
    /** @internal */
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * Send one email. Returns ['id' => 'msg_...', 'status' => 'queued'].
     *
     * @param array<string, mixed> $email from, to, subject, html or text, and optionally cc, bcc,
     *                                    reply_to, headers, attachments and tags
     * @param array{idempotency_key?: string} $options
     * @return array{id: string, status: string}
     */
    public function send(array $email, array $options = []): array
    {
        /** @var array{id: string, status: string} */
        return $this->http->request('POST', '/emails', body: $email, headers: [
            'Idempotency-Key' => $options['idempotency_key'] ?? Keys::idempotency(),
        ], idempotent: true);
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/emails/' . rawurlencode($id), idempotent: true);
    }

    /**
     * One page, newest first. Pass `next` as `after` for the following page.
     *
     * @param array{limit?: int, after?: string, q?: string, status?: string} $query
     * @return array{data: list<array<string, mixed>>, has_more: bool, next: ?string}
     */
    public function list(array $query = []): array
    {
        /** @var array{data: list<array<string, mixed>>, has_more: bool, next: ?string} */
        return $this->http->request('GET', '/emails', $query, idempotent: true);
    }

    /**
     * Every email matching the query, page by page.
     *
     * @param array{limit?: int, q?: string, status?: string} $query
     * @return \Generator<int, array<string, mixed>>
     */
    public function listAll(array $query = []): \Generator
    {
        return Pages::all(fn (?string $after) => $this->list([...$query, 'after' => $after]));
    }
}
