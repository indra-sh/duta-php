<?php

declare(strict_types=1);

namespace Duta\Tests;

use Duta\Webhook;
use Duta\WebhookVerificationException;
use PHPUnit\Framework\TestCase;

/**
 * The fixture is a delivery signed by Duta's own webhook signer, so this
 * checks the SDK against what Duta sends. The tolerance is widened to reach
 * the moment it was signed, since PHP's clock cannot be moved.
 */
final class WebhookTest extends TestCase
{
    /** @var array{secret: string, body: string, timestamp: int, headers: array<string, string>} */
    private array $f;
    private int $since;

    protected function setUp(): void
    {
        $this->f = json_decode((string) file_get_contents(__DIR__ . '/fixtures/webhook.json'), true);
        $this->since = abs(time() - $this->f['timestamp']) + 60;
    }

    public function testAcceptsADeliverySignedByDuta(): void
    {
        $event = Webhook::verify($this->f['body'], $this->f['headers'], $this->f['secret'], $this->since);
        $this->assertSame('email.delivered', $event['type']);
    }

    public function testReadsTheSvixHeadersAndAnyCase(): void
    {
        $h = $this->f['headers'];
        $svix = ['Svix-Id' => $h['svix-id'], 'Svix-Timestamp' => $h['svix-timestamp'], 'Svix-Signature' => [$h['svix-signature']]];
        $this->assertSame('email.delivered', Webhook::verify($this->f['body'], $svix, $this->f['secret'], $this->since)['type']);
    }

    public function testRefusesABodyChangedAfterSigning(): void
    {
        $this->expectException(WebhookVerificationException::class);
        Webhook::verify(str_replace('delivered', 'bounced', $this->f['body']), $this->f['headers'], $this->f['secret'], $this->since);
    }

    public function testRefusesTheWrongSecret(): void
    {
        $this->expectExceptionMessage('does not match');
        Webhook::verify($this->f['body'], $this->f['headers'], 'whsec_' . str_repeat('cd34', 8), $this->since);
    }

    public function testRefusesAnOldDelivery(): void
    {
        $this->expectExceptionMessage('outside the allowed window');
        Webhook::verify($this->f['body'], $this->f['headers'], $this->f['secret']);
    }

    public function testRefusesADeliveryWithNoSignatureHeaders(): void
    {
        $this->expectExceptionMessage('Missing');
        Webhook::verify($this->f['body'], [], $this->f['secret']);
    }
}
