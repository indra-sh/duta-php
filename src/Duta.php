<?php

declare(strict_types=1);

namespace Duta;

use Duta\Resources\ApiKeys;
use Duta\Resources\Batch;
use Duta\Resources\Domains;
use Duta\Resources\Emails;
use Duta\Resources\Logs;
use Duta\Resources\Suppressions;
use Duta\Resources\Usage;
use Duta\Resources\Webhooks;

/**
 * The Duta client.
 *
 *     $duta = new Duta\Duta(getenv('DUTA_API_KEY'));
 *     $sent = $duta->emails->send(['from' => ..., 'to' => ..., 'subject' => ..., 'html' => ...]);
 *
 * Every method returns the API's JSON as an array and throws DutaException on
 * an error.
 */
final class Duta
{
    public const VERSION = '0.2.0';

    public readonly Emails $emails;
    public readonly Batch $batch;
    public readonly Domains $domains;
    public readonly ApiKeys $apiKeys;
    public readonly Webhooks $webhooks;
    public readonly Suppressions $suppressions;
    public readonly Logs $logs;
    public readonly Usage $usage;

    /**
     * @param array{base_url?: string, timeout?: float, max_retries?: int, http_client?: \GuzzleHttp\ClientInterface} $options
     *        base_url defaults to https://api.duta.indra.sh (or DUTA_BASE_URL), timeout to 30 seconds per
     *        attempt and max_retries to 2.
     */
    public function __construct(?string $apiKey = null, array $options = [])
    {
        $key = $apiKey ?? (getenv('DUTA_API_KEY') ?: null);
        if ($key === null || $key === '') {
            throw new \InvalidArgumentException('Missing API key. Pass it to new Duta($key) or set DUTA_API_KEY.');
        }
        $http = new HttpClient($key, $options);

        $this->emails = new Emails($http);
        $this->batch = new Batch($http);
        $this->domains = new Domains($http);
        $this->apiKeys = new ApiKeys($http);
        $this->webhooks = new Webhooks($http);
        $this->suppressions = new Suppressions($http);
        $this->logs = new Logs($http);
        $this->usage = new Usage($http);
    }
}
