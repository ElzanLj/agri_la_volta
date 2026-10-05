<?php

declare(strict_types=1);

namespace App\Domain;

use RuntimeException;

/** Input rejected by validation. errors() maps field name => error code. */
final class ValidationException extends RuntimeException
{
    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $context extra details for the caller (e.g. the minimum stay)
     */
    public function __construct(private array $errors, private array $context = [])
    {
        parent::__construct('Validation failed: ' . json_encode($errors));
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }
}
