<?php

declare(strict_types=1);

namespace Duta\Tests;

use Duta\Duta;
use Duta\Laravel\DutaServiceProvider;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\Mail;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('laravel')]
final class LaravelTest extends TestCase
{
    private Fake $fake;

    protected function getPackageProviders($app): array
    {
        return [DutaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('mail.default', 'duta');
        $app['config']->set('mail.from', ['address' => 'resit@kedai.my', 'name' => 'Kedai']);
        $app['config']->set('services.duta.key', 'duta_laravel');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->fake = new Fake([Fake::json(202, ['id' => 'msg_laravel', 'status' => 'queued'])]);
        $this->app->instance(Duta::class, $this->fake->duta);
    }

    public function testAMailableGoesThroughDutaWithEverythingItSets(): void
    {
        Mail::to([['email' => 'siti@example.com', 'name' => 'Siti Aminah']])
            ->cc('boss@kedai.my')
            ->bcc('audit@kedai.my')
            ->send(new ReceiptMail());

        $this->assertCount(1, $this->fake->history);
        $request = $this->fake->request(0);
        $this->assertSame('/v1/emails', $request->getUri()->getPath());
        $this->assertSame('receipt-1042', $request->getHeaderLine('Idempotency-Key'));

        $body = $this->fake->body(0);
        $this->assertSame(['email' => 'resit@kedai.my', 'name' => 'Kedai'], $body['from']);
        $this->assertSame([['email' => 'siti@example.com', 'name' => 'Siti Aminah']], $body['to']);
        $this->assertSame([['email' => 'boss@kedai.my']], $body['cc']);
        $this->assertSame([['email' => 'audit@kedai.my']], $body['bcc']);
        $this->assertSame([['email' => 'bantuan@kedai.my']], $body['reply_to']);
        $this->assertSame('Resit #1042', $body['subject']);
        $this->assertStringContainsString('Terima kasih', $body['html']);
        $this->assertSame([['name' => 'type', 'value' => 'receipt']], $body['tags']);
        $this->assertSame(['X-Entity-Ref-ID' => 'order-1042'], $body['headers']);
        $this->assertSame('invois.txt', $body['attachments'][0]['filename']);
        $this->assertSame('invoice body', base64_decode($body['attachments'][0]['content']));
        $this->assertStringStartsWith('text/plain', $body['attachments'][0]['content_type']);
    }

    public function testTheClientIsBuiltFromServicesConfig(): void
    {
        $this->app->forgetInstance(Duta::class);
        $this->assertInstanceOf(Duta::class, $this->app->make(Duta::class));
    }
}

final class ReceiptMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Resit #1042', replyTo: ['bantuan@kedai.my']);
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>Terima kasih atas pembelian anda.</p>');
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'X-Entity-Ref-ID' => 'order-1042',
            'X-Duta-Tag-type' => 'receipt',
            'X-Duta-Idempotency-Key' => 'receipt-1042',
        ]);
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => 'invoice body', 'invois.txt')->withMime('text/plain')];
    }
}
