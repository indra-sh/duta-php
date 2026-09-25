# duta/duta-php

The official PHP SDK for [Duta](https://duta.indra.sh), transactional email
for Malaysia, with a Laravel mail driver.

- PHP 8.1+. One dependency, Guzzle. Laravel 12 and 13.
- **Laravel**: set `MAIL_MAILER=duta` and every Mailable sends through Duta.
- Retries rate limits and server errors safely: every send carries an
  idempotency key, so a retry can never send twice.

## Upgrading from 0.1.x

0.2.0 is a new SDK for Duta's current API, not an update of 0.1.x, which was
written for an earlier version of Duta that no longer runs.

## Install

```sh
composer require duta/duta-php
```

## Laravel

The package registers itself. Add your key to `.env` and choose the mailer:

```dotenv
DUTA_API_KEY=duta_xxxxxxxxxxxxxxxx
MAIL_MAILER=duta
```

Your existing mail code now sends through Duta:

```php
Mail::to($order->customer)->send(new OrderReceipt($order));
```

Optional: put the key in `config/services.php` instead of the environment:

```php
'duta' => ['key' => env('DUTA_API_KEY')],
```

A Mailable can set two Duta-specific headers:

```php
public function headers(): Headers
{
    return new Headers(text: [
        'X-Duta-Tag-type' => 'receipt',                        // a tag: type=receipt
        'X-Duta-Idempotency-Key' => "receipt-{$this->order->id}", // safe if the job runs twice
    ]);
}
```

For everything else, inject `Duta\Duta`:

```php
public function __construct(private \Duta\Duta $duta) {}
```

## Send an email

```php
$duta = new Duta\Duta(getenv('DUTA_API_KEY'));

$sent = $duta->emails->send([
    'from' => 'Kedai <resit@kedai.my>',
    'to' => 'siti@example.com',
    'subject' => 'Resit #1042',
    'html' => '<p>Terima kasih.</p>',
]);

echo $sent['id'];
```

Errors throw `Duta\DutaException`. `getErrorCode()` is Duta's
[error code](https://docs.duta.indra.sh/guides/errors/) and `getRequestId()`
finds the request on the Logs screen.

```php
try {
    $duta->emails->send($email, ['idempotency_key' => "receipt-{$order->id}"]);
} catch (Duta\DutaException $e) {
    report($e->getErrorCode() . ' ' . $e->getRequestId());
}
```

## Batch and paging

```php
$duta->batch->send([$first, $second], ['validation' => 'permissive']);

foreach ($duta->emails->listAll(['status' => 'bounced']) as $email) {
    echo $email['id'], PHP_EOL;
}
```

## Verify webhooks

```php
$event = Duta\Webhook::verify(
    $request->getContent(),        // the raw body
    $request->headers->all(),
    config('services.duta.webhook_secret'),
);
```

It throws `Duta\WebhookVerificationException` when the signature is wrong or
the delivery is more than five minutes old.

## Everything else

| | |
|---|---|
| `emails` | `send`, `get`, `list`, `listAll` |
| `batch` | `send` |
| `domains` | `create`, `list`, `get`, `verify`, `remove` |
| `apiKeys` | `create`, `list`, `remove` |
| `webhooks` | `create`, `list`, `get`, `remove`, `enable`, `test`, `deliveries`, `verify` |
| `suppressions` | `create`, `list`, `listAll`, `remove` |
| `logs` | `list`, `listAll`, `get` |
| `usage` | `get` |

Options: `new Duta\Duta($key, ['base_url' => ..., 'timeout' => 30.0, 'max_retries' => 2])`.

Full documentation: https://docs.duta.indra.sh
