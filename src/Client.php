<?php

declare(strict_types=1);

namespace Duta;

/**
 * Duta API client.
 *
 * Example:
 *
 *     $duta = new \Duta\Client('duta_live_xxx');
 *     $duta->emails->send([
 *         'from' => 'hello@yourdomain.com',
 *         'to' => 'user@example.com',
 *         'subject' => 'Hello',
 *         'html' => '<p>It works!</p>',
 *     ]);
 */
class Client
{
    public Emails $emails;

    private string $apiKey;
    private string $baseUrl;
    private int $timeout;

    public function __construct(string $apiKey, string $baseUrl = 'https://api.duta.indra.sh', int $timeout = 30)
    {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('A Duta API key is required. Create one at https://app.duta.indra.sh.');
        }
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
        $this->emails = new Emails($this);
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, ?array $body = null): array
    {
        $ch = curl_init($this->baseUrl . $path);
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
        ];

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new DutaException('network_error', $err, 0);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $parsed = $raw === '' ? [] : json_decode((string) $raw, true);

        if ($status < 200 || $status >= 300) {
            throw DutaException::fromResponse($status, $parsed);
        }

        return is_array($parsed) ? $parsed : [];
    }
}
