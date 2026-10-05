<?php

declare(strict_types=1);

namespace App\Mail;

use RuntimeException;

/** There is nobody to write to (e.g. a phone booking without an e-mail address): the row is skipped. */
final class NoRecipientException extends RuntimeException
{
}
