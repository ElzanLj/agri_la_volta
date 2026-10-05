<?php

declare(strict_types=1);

namespace App\Domain;

use RuntimeException;

/** Input rejected by validation. errors() maps field name => error code. */
final class ValidationException extends RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(private array $errors)
    {
        parent::__construct('Validation failed: ' . json_encode($errors));
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
