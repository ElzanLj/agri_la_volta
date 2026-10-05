<?php

declare(strict_types=1);

namespace App\Domain;

use RuntimeException;

/** A database lock could not be obtained in time; the operation can be retried. */
final class BusyException extends RuntimeException
{
}
