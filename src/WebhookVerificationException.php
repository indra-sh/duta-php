<?php

declare(strict_types=1);

namespace Duta;

/** The delivery is not from Duta, was changed or is too old. Never trust its body. */
class WebhookVerificationException extends \RuntimeException
{
}
