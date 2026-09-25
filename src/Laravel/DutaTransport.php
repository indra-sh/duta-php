<?php

declare(strict_types=1);

namespace Duta\Laravel;

use Duta\Duta;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;

/**
 * A Symfony Mailer transport that sends through Duta's API, which is what
 * Laravel's mail system runs on. Everything a Mailable sets is carried over:
 * from, to, cc, bcc, reply-to, subject, both bodies, attachments and custom
 * headers.
 *
 * Two headers are read rather than sent:
 * - X-Duta-Tag-<name>: <value> becomes a tag.
 * - X-Duta-Idempotency-Key: <key> makes the send safe to repeat, such as from
 *   a queued job that may run twice.
 */
final class DutaTransport extends AbstractTransport
{
    /** Set by the transport or by Duta itself, never passed through as custom headers. */
    private const RESERVED = [
        'from', 'to', 'cc', 'bcc', 'reply-to', 'sender', 'subject', 'date', 'message-id',
        'mime-version', 'content-type', 'content-transfer-encoding', 'return-path',
    ];

    public function __construct(private readonly Duta $duta)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        [$payload, $idempotencyKey] = self::payload($email, $message);

        $options = $idempotencyKey !== null ? ['idempotency_key' => $idempotencyKey] : [];
        $sent = $this->duta->emails->send($payload, $options);

        $message->setMessageId($sent['id']);
    }

    /**
     * @return array{0: array<string, mixed>, 1: ?string}
     * @internal Public for tests.
     */
    public static function payload(Email $email, ?SentMessage $sent = null): array
    {
        // As objects, not "Name <email>" strings, so a name with quotes or
        // commas in it arrives exactly as written.
        $address = static fn (Address $a): array => $a->getName() !== ''
            ? ['email' => $a->getAddress(), 'name' => $a->getName()]
            : ['email' => $a->getAddress()];
        $addresses = static fn (array $list): array => array_map($address, $list);

        $from = $email->getFrom()[0] ?? $sent?->getEnvelope()->getSender();
        // The envelope carries every recipient, including bcc, which the
        // message headers do not.
        $to = $email->getTo() ?: ($sent ? array_values(array_filter(
            $sent->getEnvelope()->getRecipients(),
            static fn (Address $r) => !in_array($r, [...$email->getCc(), ...$email->getBcc()], false),
        )) : []);

        $payload = [
            'from' => $from ? $address($from) : null,
            'to' => $addresses($to),
            'subject' => (string) $email->getSubject(),
        ];
        if ($email->getCc()) {
            $payload['cc'] = $addresses($email->getCc());
        }
        if ($email->getBcc()) {
            $payload['bcc'] = $addresses($email->getBcc());
        }
        if ($email->getReplyTo()) {
            $payload['reply_to'] = $addresses($email->getReplyTo());
        }
        if ($email->getHtmlBody() !== null) {
            $payload['html'] = self::body($email->getHtmlBody());
        }
        if ($email->getTextBody() !== null) {
            $payload['text'] = self::body($email->getTextBody());
        }

        $attachments = [];
        foreach ($email->getAttachments() as $part) {
            $attachments[] = self::attachment($part);
        }
        if ($attachments) {
            $payload['attachments'] = $attachments;
        }

        $headers = [];
        $tags = [];
        $idempotencyKey = null;
        foreach ($email->getHeaders()->all() as $header) {
            $name = $header->getName();
            $lower = strtolower($name);
            if (in_array($lower, self::RESERVED, true)) {
                continue;
            }
            $value = $header->getBodyAsString();
            if ($lower === 'x-duta-idempotency-key') {
                $idempotencyKey = $value;
            } elseif (str_starts_with($lower, 'x-duta-tag-')) {
                $tags[] = ['name' => substr($name, strlen('x-duta-tag-')), 'value' => $value];
            } else {
                $headers[$name] = $value;
            }
        }
        if ($headers) {
            $payload['headers'] = $headers;
        }
        if ($tags) {
            $payload['tags'] = $tags;
        }

        return [$payload, $idempotencyKey];
    }

    /** @return array<string, string> */
    private static function attachment(DataPart $part): array
    {
        $attachment = [
            'filename' => $part->getFilename() ?? 'attachment',
            'content' => base64_encode($part->getBody()),
            'content_type' => $part->getContentType(),
        ];
        if ($part->hasContentId()) {
            $attachment['content_id'] = $part->getContentId();
        }
        return $attachment;
    }

    /** @param resource|string $body */
    private static function body(mixed $body): string
    {
        return is_resource($body) ? (string) stream_get_contents($body) : (string) $body;
    }

    public function __toString(): string
    {
        return 'duta';
    }
}
