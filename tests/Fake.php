<?php

declare(strict_types=1);

namespace Duta\Tests;

use Duta\Duta;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/** A Duta client whose requests are recorded and answered from a queue. */
final class Fake
{
    /** @var list<array{request: RequestInterface}> */
    public array $history = [];
    public MockHandler $mock;
    public Duta $duta;

    /** @param list<Response|\Throwable> $answers */
    public function __construct(array $answers = [], ?\Closure $fallback = null, array $options = [])
    {
        $fallback ??= static fn () => new Response(200, [], '{"data":[],"has_more":false,"next":null}');
        $this->mock = new MockHandler($answers);
        $mock = $this->mock;
        $handler = static function (RequestInterface $request, array $options) use ($mock, $fallback) {
            return $mock->count() > 0 ? $mock($request, $options) : \GuzzleHttp\Promise\Create::promiseFor($fallback());
        };
        $stack = HandlerStack::create($handler);
        $stack->push(Middleware::history($this->history));
        $this->duta = new Duta('duta_test', [
            'http_client' => new Client(['handler' => $stack]),
            'sleep' => static fn (int $ms) => null,
            ...$options,
        ]);
    }

    public function request(int $i): RequestInterface
    {
        return $this->history[$i]['request'];
    }

    /** @return array<mixed> */
    public function body(int $i): array
    {
        return json_decode((string) $this->request($i)->getBody(), true);
    }

    public static function json(int $status, array $body, array $headers = []): Response
    {
        return new Response($status, ['Content-Type' => 'application/json', ...$headers], json_encode($body));
    }

    public static function error(int $status, string $code, array $headers = []): Response
    {
        return self::json($status, [
            'statusCode' => $status,
            'name' => 'x',
            'message' => "{$code} happened",
            'code' => $code,
            'request_id' => 'req_body',
        ], ['X-Request-Id' => 'req_header', ...$headers]);
    }
}
