# Duta PHP SDK

Official PHP client for [Duta](https://duta.indra.sh). Uses cURL, no third-party dependencies.

## Install

```bash
composer require duta/duta-php
```

## Quickstart

```php
require 'vendor/autoload.php';

$duta = new \Duta\Client('duta_live_xxx');

$result = $duta->emails->send([
    'from' => 'hello@yourdomain.com',
    'to' => 'user@example.com',
    'subject' => 'Welcome to Duta',
    'html' => '<p>Thanks for signing up!</p>',
]);

echo "Sent: " . $result['id'];
```

Get an API key from the [dashboard](https://app.duta.indra.sh). The sender domain must be verified first.

## Error handling

Methods throw `\Duta\DutaException` on failure:

```php
use Duta\DutaException;

try {
    $duta->emails->send([ /* ... */ ]);
} catch (DutaException $e) {
    echo $e->statusCode . ' ' . $e->name . ': ' . $e->getMessage();
    // $e->name: authentication_error | permission_denied | rate_limit_exceeded | ...
}
```

## API

### `new \Duta\Client(string $apiKey, string $baseUrl = ..., int $timeout = 30)`

### `$duta->emails->send(array $params)`

`$params` keys: `from`, `to` (string or array), `subject`, `html`, `text`, `reply_to`, `tags` (assoc array). Returns an array with `id` and `status`.

### `$duta->emails->get(string $id)`

Retrieve one email. Requires a full-access API key.

### `$duta->emails->list(int $page = 1, int $limit = 20)`

List emails, newest first. Requires a full-access API key.

## License

MIT
