<?php

declare(strict_types=1);

namespace App\Database;

use App\Domain\BusyException;
use LogicException;
use PDO;
use PDOException;
use Throwable;

/**
 * Runs a callable in a READ COMMITTED transaction: every statement sees the latest committed
 * data, also after waiting for a lock. Lock waits are capped; a timeout or deadlock is reported
 * as BusyException (safe to retry).
 */
final class TransactionRunner
{
    private const MYSQL_LOCK_WAIT_TIMEOUT = 1205;
    private const MYSQL_DEADLOCK = 1213;

    public function __construct(private PDO $db, private int $lockWaitSeconds = 10)
    {
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public function run(callable $fn): mixed
    {
        if ($this->db->inTransaction()) {
            throw new LogicException('Operations cannot be nested in another transaction.');
        }

        $this->db->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $this->db->exec('SET SESSION innodb_lock_wait_timeout = ' . $this->lockWaitSeconds);
        $this->db->beginTransaction();

        try {
            $result = $fn();
            $this->db->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($e instanceof PDOException && in_array($e->errorInfo[1] ?? 0, [self::MYSQL_LOCK_WAIT_TIMEOUT, self::MYSQL_DEADLOCK], true)) {
                throw new BusyException('The database is busy, retry shortly.', 0, $e);
            }
            throw $e;
        }
    }
}
