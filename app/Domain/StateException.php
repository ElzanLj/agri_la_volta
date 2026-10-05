<?php

declare(strict_types=1);

namespace App\Domain;

use RuntimeException;

/** The entity does not exist or is in a state that does not allow the operation. */
final class StateException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
