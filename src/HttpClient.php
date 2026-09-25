<?php

declare(strict_types=1);

namespace Duta;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TransferException;
use Psr\Http\Message\ResponseInterface;

/**
 * Authentication, timeouts, retries and the error shape, shared by every
 * resource. A 429 is retried for any request, after Retry-After. A 5xx or a
 * network failure is retried only when the request is safe to repeat, since
 * the first attempt may have taken effect.
 *
 * @internal
 */
final class HttpClient
{
    private readonly string $baseUrl;
    private readonly ClientInterface $http;
    private readonly int $maxRetries;
    /** @var \Closure(int): void */
    private readonly \Closure $sleep;

    /**
     * @param array{base_url?: string, timeout?: float, max_retries?: int, http_client?: ClientInterface, sleep?: callable(int): void} $options
     */
    public function __construct(private readonly string $apiKey, array $options = [])
    {
        $base = $options['base_url'] ?? (getenv('DUTA_BASE_URL') ?: 'https://api.duta.indra.sh');
        $this->baseUrl = rtrim($base, '/');
        $this->http = $options['http_client'] ?? new Client(['timeout' => $options['timeout'] ?? 30.0]);
        $this->maxRetries = max(0, $options['max_retries'] ?? 2);
        $sleep = $options['sleep'] ?? static fn (int $ms) => usleep($ms * 1000);
        $this->sleep = \Closure::fromCallable($sleep);
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, string|null> $headers
     * @return array<mixed>
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        mixed $body = null,
        array $headers = [],
        bool $idempotent = false,
    ): array {
        $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');
        $url = $this->baseUrl . '/v1' . $path . ($query ? '?' . http_build_query($query) : '');

        $sent = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'User-Agent' => 'duta-php/' . Duta::VERSION,
            'Accept' => 'application/json',
        ];
        foreach ($headers as $name => $value) {
            if ($value !== null) {
                $sent[$name] = $value;
            }
        }
        $options = ['headers' => $sent, 'http_errors' => false];
        if ($body !== null) {
            $options['body'] = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $options['headers']['Content-Type'] = 'application/json';
        }

        for ($attempt = 0; ; $attempt++) {
            try {
                $response = $this->http->request($method, $url, $options);
            } catch (ConnectException | TransferException $e) {
                if ($idempotent && $attempt < $this->maxRetries) {
                    ($this->sleep)($this->backoffMs($attempt));
                    continue;
                }
                throw new DutaException('Could not reach Duta: ' . $e->getMessage(), null, 'network_error', 'network_error', null, null, $e);
            }

            $status = $response->getStatusCode();
            $retryable = $status === 429 || ($status >= 500 && $idempotent);
            if ($retryable && $attempt < $this->maxRetries) {
                ($this->sleep)($this->retryAfterMs($response) ?? $this->backoffMs($attempt));
                continue;
            }

            $text = (string) $response->getBody();
            $json = $text === '' ? [] : json_decode($text, true);
            if ($status >= 200 && $status < 300) {
                return is_array($json) ? $json : [];
            }

            $error = is_array($json) ? $json : [];
            throw new DutaException(
                (string) ($error['message'] ?? "Duta answered {$status}"),
                $status,
                (string) ($error['name'] ?? 'application_error'),
                (string) ($error['code'] ?? 'internal_error'),
                $error['request_id'] ?? ($response->getHeaderLine('X-Request-Id') ?: null),
                isset($error['detail']) && is_array($error['detail']) ? $error['detail'] : null,
            );
        }
    }

    /** 0.5s, 1s, 2s... with jitter, so clients that failed together do not retry together. */
    private function backoffMs(int $attempt): int
    {
        $base = min(8000, 500 * 2 ** $attempt);
        return intdiv($base, 2) + random_int(0, intdiv($base, 2));
    }

    private function retryAfterMs(ResponseInterface $response): ?int
    {
        $header = $response->getHeaderLine('Retry-After');
        if ($header === '') {
            return null;
        }
        if (is_numeric($header)) {
            return (int) (min((float) $header, 60) * 1000);
        }
        $at = strtotime($header);
        return $at === false ? null : max(0, min(($at - time()) * 1000, 60000));
    }
}
