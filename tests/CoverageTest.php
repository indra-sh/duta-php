<?php

declare(strict_types=1);

namespace Duta\Tests;

use PHPUnit\Framework\TestCase;

/** The SDK calls exactly the operations in Duta's OpenAPI spec, no more and no fewer. */
final class CoverageTest extends TestCase
{
    public function testCallsExactlyTheOperationsInTheSpec(): void
    {
        $fake = new Fake();
        $d = $fake->duta;
        $email = ['from' => 'Kedai <resit@kedai.my>', 'to' => 'siti@example.com', 'subject' => 'Resit', 'text' => 'Hi'];

        $d->emails->send($email);
        $d->emails->get('msg_1');
        $d->emails->list();
        $d->batch->send([$email]);
        $d->domains->create(['name' => 'kedai.my']);
        $d->domains->list();
        $d->domains->get('dom_1');
        $d->domains->verify('dom_1');
        $d->domains->remove('dom_1');
        $d->apiKeys->create(['name' => 'CI']);
        $d->apiKeys->list();
        $d->apiKeys->remove('key_1');
        $d->webhooks->create(['endpoint' => 'https://kedai.my/hook']);
        $d->webhooks->list();
        $d->webhooks->get('whk_1');
        $d->webhooks->remove('whk_1');
        $d->webhooks->enable('whk_1');
        $d->webhooks->test('whk_1');
        $d->webhooks->deliveries('whk_1');
        $d->suppressions->list();
        $d->suppressions->create('gone@example.com');
        $d->suppressions->remove('gone@example.com');
        $d->logs->list();
        $d->logs->get('req_1');
        $d->usage->get();

        $spec = json_decode((string) file_get_contents(__DIR__ . '/fixtures/openapi.json'), true);
        // Fixed paths first: /emails/batch also matches /emails/{id}.
        $templates = array_keys($spec['paths']);
        usort($templates, static fn (string $a, string $b) => [substr_count($a, '{'), $a] <=> [substr_count($b, '{'), $b]);

        $made = [];
        foreach ($fake->history as $entry) {
            $path = preg_replace('#^/v1#', '', $entry['request']->getUri()->getPath());
            $match = 'UNKNOWN ' . $path;
            foreach ($templates as $t) {
                if (preg_match('#^' . preg_replace('/\\\{[^}]+\\\}/', '[^/]+', preg_quote($t, '#')) . '$#', $path)) {
                    $match = $t;
                    break;
                }
            }
            $made[] = strtolower($entry['request']->getMethod()) . ' ' . $match;
        }

        $inSpec = [];
        foreach ($spec['paths'] as $path => $ops) {
            foreach (array_keys($ops) as $method) {
                $inSpec[] = "{$method} {$path}";
            }
        }
        // The archived body is the dashboard's preview, not something an SDK user needs.
        $inSpec = array_values(array_diff($inSpec, ['get /emails/{id}/body']));

        $made = array_values(array_unique($made));
        sort($made);
        sort($inSpec);
        $this->assertSame($inSpec, $made);
    }
}
