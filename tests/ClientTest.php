<?php

declare(strict_types=1);

namespace Duta\Tests;

use Duta\Duta;
use Duta\DutaException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private array $email = ['from' => 'Kedai <resit@kedai.my>', 'to' => 'siti@example.com', 'subject' => 'Resit', 'text' => 'Terima kasih'];

    public function testSendsWithTheKeyAUserAgentAndAnIdempotencyKeyItMadeItself(): void
    {
        $fake = new Fake([Fake::json(202, ['id' => 'msg_1', 'status' => 'queued'])]);
        $sent = $fake->duta->emails->send($this->email);

        $this->assertSame(['id' => 'msg_1', 'status' => 'queued'], $sent);
        $request = $fake->request(0);
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api.duta.indra.sh/v1/emails', (string) $request->getUri());
        $this->assertSame('Bearer duta_test', $request->getHeaderLine('Authorization'));
        $this->assertStringStartsWith('duta-php/', $request->getHeaderLine('User-Agent'));
        $this->assertMatchesRegularExpression('/^duta-php-[0-9a-f-]{36}$/', $request->getHeaderLine('Idempotency-Key'));
        $this->assertSame($this->email, $fake->body(0));
    }

    public function testUsesTheIdempotencyKeyItIsGiven(): void
    {
        $fake = new Fake();
        $fake->duta->emails->send($this->email, ['idempotency_key' => 'receipt-1042']);
        $this->assertSame('receipt-1042', $fake->request(0)->getHeaderLine('Idempotency-Key'));
    }

    public function testThrowsTheApiErrorWithItsCodeAndRequestId(): void
    {
        $fake = new Fake([Fake::error(422, 'validation_failed')]);
        try {
            $fake->duta->emails->send($this->email);
            $this->fail('expected an exception');
        } catch (DutaException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertSame('validation_failed', $e->getErrorCode());
            $this->assertSame('validation_failed happened', $e->getMessage());
            $this->assertSame('req_body', $e->getRequestId());
        }
    }

    public function testFallsBackToTheRequestIdHeader(): void
    {
        $fake = new Fake([new Response(502, ['X-Request-Id' => 'req_h'], 'upstream broke')], null, ['max_retries' => 0]);
        try {
            $fake->duta->domains->list();
            $this->fail('expected an exception');
        } catch (DutaException $e) {
            $this->assertSame(502, $e->getStatusCode());
            $this->assertSame('req_h', $e->getRequestId());
        }
    }

    public function testRetriesA429ForAnyRequest(): void
    {
        $fake = new Fake([Fake::error(429, 'rate_limited', ['Retry-After' => '0']), Fake::json(201, ['id' => 'dom_1'])]);
        $this->assertSame(['id' => 'dom_1'], $fake->duta->domains->create(['name' => 'kedai.my']));
        $this->assertCount(2, $fake->history);
    }

    public function testRetriesASendAfterA5xxWithTheSameIdempotencyKey(): void
    {
        $fake = new Fake([Fake::error(503, 'platform_halted'), Fake::json(202, ['id' => 'msg_2', 'status' => 'queued'])]);
        $this->assertSame('msg_2', $fake->duta->emails->send($this->email)['id']);
        $this->assertCount(2, $fake->history);
        $this->assertSame(
            $fake->request(0)->getHeaderLine('Idempotency-Key'),
            $fake->request(1)->getHeaderLine('Idempotency-Key'),
        );
    }

    public function testNeverRetriesA5xxOnAWriteThatMayHaveTakenEffect(): void
    {
        $fake = new Fake([Fake::error(500, 'internal_error'), Fake::json(201, [])]);
        try {
            $fake->duta->apiKeys->create(['name' => 'CI']);
            $this->fail('expected an exception');
        } catch (DutaException $e) {
            $this->assertSame('internal_error', $e->getErrorCode());
        }
        $this->assertCount(1, $fake->history);
    }

    public function testRetriesAReadAfterANetworkFailureButNotAWrite(): void
    {
        $down = static fn () => new ConnectException('socket hang up', new Request('GET', '/'));

        $read = new Fake([$down(), Fake::json(200, ['id' => 'msg_3'])]);
        $this->assertSame(['id' => 'msg_3'], $read->duta->emails->get('msg_3'));

        $write = new Fake([$down()]);
        try {
            $write->duta->webhooks->test('whk_1');
            $this->fail('expected an exception');
        } catch (DutaException $e) {
            $this->assertNull($e->getStatusCode());
            $this->assertSame('network_error', $e->getErrorCode());
        }
        $this->assertCount(1, $write->history);
    }

    public function testPagesThroughEveryEmailWithListAll(): void
    {
        $fake = new Fake([
            Fake::json(200, ['data' => [['id' => 'msg_a'], ['id' => 'msg_b']], 'has_more' => true, 'next' => 'msg_b']),
            Fake::json(200, ['data' => [['id' => 'msg_c']], 'has_more' => false, 'next' => null]),
        ]);
        $ids = array_map(static fn (array $e) => $e['id'], iterator_to_array($fake->duta->emails->listAll(['limit' => 2]), false));
        $this->assertSame(['msg_a', 'msg_b', 'msg_c'], $ids);
        parse_str($fake->request(1)->getUri()->getQuery(), $query);
        $this->assertSame(['limit' => '2', 'after' => 'msg_b'], $query);
    }

    public function testTakesABaseUrlWithOrWithoutATrailingSlash(): void
    {
        $fake = new Fake([], null, ['base_url' => 'http://localhost:8787/']);
        $fake->duta->usage->get();
        $this->assertSame('http://localhost:8787/v1/usage', (string) $fake->request(0)->getUri());
    }

    public function testRefusesToStartWithoutAKey(): void
    {
        $saved = getenv('DUTA_API_KEY');
        putenv('DUTA_API_KEY');
        try {
            $this->expectException(\InvalidArgumentException::class);
            new Duta();
        } finally {
            if ($saved !== false) {
                putenv("DUTA_API_KEY={$saved}");
            }
        }
    }
}
