<?php

declare(strict_types=1);

namespace Duta\Resources;

use Duta\HttpClient;

final class Usage
{
    /** @internal */
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * This month's usage, the plan's limits and the last 30 days by status.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->http->request('GET', '/usage', idempotent: true);
    }
}
