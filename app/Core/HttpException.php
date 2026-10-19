<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Thrown to stop a request with a given HTTP status; rendered as a page or a JSON error.
 */
class HttpException extends \RuntimeException
{
    /** @param array<string, mixed> $errors */
    public function __construct(
        public readonly int $status,
        string $message = '',
        public readonly array $errors = [],
        public readonly string $errorCode = '',
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status);
    }

    public static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'The request could not be understood.',
            401 => 'Please sign in to continue.',
            403 => 'You do not have permission to do that.',
            404 => 'The page you are looking for does not exist.',
            405 => 'This action is not allowed here.',
            409 => 'This item was changed somewhere else.',
            413 => 'The request is too large.',
            419 => 'Your session expired. Please reload the page and try again.',
            422 => 'Some fields need your attention.',
            429 => 'Too many attempts. Please wait a moment and try again.',
            default => 'Something went wrong on our side. Please try again.',
        };
    }
}
