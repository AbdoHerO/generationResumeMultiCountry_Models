<?php

declare(strict_types=1);

namespace App\Services\Ai;

/** A failure of the AI service, with a message that is safe to show to users. */
final class AiException extends \RuntimeException
{
    /** @param int $retryAfter seconds to wait before retrying (rate limits), 0 when unknown */
    public function __construct(string $message, public readonly string $reason = 'error', public readonly int $retryAfter = 0)
    {
        parent::__construct($message);
    }
}
