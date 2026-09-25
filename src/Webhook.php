<?php

declare(strict_types=1);

namespace Duta;

/**
 * Verify a webhook delivery from Duta. Deliveries are signed per the Standard
 * Webhooks spec, as Resend's are: HMAC-SHA256 over "id.timestamp.body", keyed
 * with the base64 part of the whsec_ secret, sent as "v1,<base64>".
 */
final class Webhook
{
    /**
     * Returns the decoded event, or throws WebhookVerificationException.
     *
     * @param string $payload the raw request body, before any JSON decoding
     * @param array<string, string|list<string>> $headers the request headers (webhook-* or svix-*)
     * @return array<string, mixed>
     */
    public static function verify(string $payload, array $headers, string $secret, int $toleranceSeconds = 300): array
    {
        $h = [];
        foreach ($headers as $name => $value) {
            $h[strtolower((string) $name)] = is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
        }
        $id = $h['webhook-id'] ?? $h['svix-id'] ?? '';
        $timestamp = $h['webhook-timestamp'] ?? $h['svix-timestamp'] ?? '';
        $signature = $h['webhook-signature'] ?? $h['svix-signature'] ?? '';
        if ($id === '' || $timestamp === '' || $signature === '') {
            throw new WebhookVerificationException('Missing webhook-id, webhook-timestamp or webhook-signature header');
        }

        if (!ctype_digit($timestamp) || abs(time() - (int) $timestamp) > $toleranceSeconds) {
            throw new WebhookVerificationException('Timestamp is outside the allowed window');
        }

        $key = base64_decode(str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret, true);
        if ($key === false) {
            throw new WebhookVerificationException('Signing secret is not valid');
        }
        $expected = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$payload}", $key, true));

        // Several signatures may be sent, space separated, during a secret rotation.
        foreach (explode(' ', $signature) as $part) {
            [$version, $sig] = array_pad(explode(',', $part, 2), 2, '');
            if ($version === 'v1' && hash_equals($expected, $sig)) {
                $event = json_decode($payload, true);
                if (!is_array($event)) {
                    throw new WebhookVerificationException('Payload is not JSON');
                }
                return $event;
            }
        }
        throw new WebhookVerificationException('Signature does not match');
    }
}
