<?php

declare(strict_types=1);

namespace Duta\Resources;

/** @internal */
final class Pages
{
    /**
     * @param callable(?string): array{data: list<array<string, mixed>>, has_more: bool, next: ?string} $page
     * @return \Generator<int, array<string, mixed>>
     */
    public static function all(callable $page): \Generator
    {
        $after = null;
        do {
            $result = $page($after);
            yield from $result['data'];
            $after = ($result['has_more'] ?? false) ? ($result['next'] ?? null) : null;
        } while ($after !== null);
    }
}
