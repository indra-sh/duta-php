<?php

declare(strict_types=1);

namespace Duta;

/**
 * The "emails" resource: send, retrieve, and list emails.
 */
class Emails
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Send a transactional email.
     *
     * Accepts: from, to (string or array), subject, html, text, reply_to, tags (assoc array).
     * Returns an array with "id" and "status". Throws DutaException on failure.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function send(array $params): array
    {
        $body = [
            'from' => $params['from'],
            'to' => $params['to'],
            'subject' => $params['subject'],
        ];
        if (isset($params['html'])) {
            $body['html'] = $params['html'];
        }
        if (isset($params['text'])) {
            $body['text'] = $params['text'];
        }
        if (isset($params['reply_to'])) {
            $body['replyTo'] = $params['reply_to'];
        }
        if (isset($params['tags'])) {
            $body['tags'] = $params['tags'];
        }

        return $this->client->request('POST', '/v1/email/send', $body);
    }

    /**
     * Retrieve a single email by ID. Requires a full-access API key.
     *
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->client->request('GET', '/v1/email/' . rawurlencode($id));
    }

    /**
     * List emails, most recent first. Requires a full-access API key.
     *
     * @return array<string, mixed>
     */
    public function list(int $page = 1, int $limit = 20): array
    {
        return $this->client->request('GET', "/v1/email?page={$page}&limit={$limit}");
    }
}
