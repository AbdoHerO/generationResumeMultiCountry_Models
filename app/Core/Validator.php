<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Small validation helper for forms. Collects one message per field.
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data)
    {
    }

    public function value(string $field): string
    {
        $v = $this->data[$field] ?? '';
        return is_scalar($v) ? trim((string) $v) : '';
    }

    public function required(string $field, string $label): self
    {
        if ($this->value($field) === '') {
            $this->add($field, "$label is required.");
        }
        return $this;
    }

    public function email(string $field): self
    {
        $v = $this->value($field);
        if ($v !== '' && (filter_var($v, FILTER_VALIDATE_EMAIL) === false || mb_strlen($v) > 190)) {
            $this->add($field, 'Enter a valid e-mail address.');
        }
        return $this;
    }

    public function max(string $field, int $max, string $label): self
    {
        if (mb_strlen($this->value($field)) > $max) {
            $this->add($field, "$label must be at most $max characters.");
        }
        return $this;
    }

    public function password(string $field): self
    {
        $v = (string) ($this->data[$field] ?? '');
        if (mb_strlen($v) < 8) {
            $this->add($field, 'Use at least 8 characters.');
        } elseif (mb_strlen($v) > 200) {
            $this->add($field, 'Use at most 200 characters.');
        } elseif (!preg_match('/[A-Za-z]/', $v) || !preg_match('/[0-9]/', $v)) {
            $this->add($field, 'Use at least one letter and one number.');
        }
        return $this;
    }

    public function confirmed(string $field, string $confirmation): self
    {
        if ((string) ($this->data[$field] ?? '') !== (string) ($this->data[$confirmation] ?? '')) {
            $this->add($confirmation, 'The two passwords do not match.');
        }
        return $this;
    }

    public function add(string $field, string $message): self
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
