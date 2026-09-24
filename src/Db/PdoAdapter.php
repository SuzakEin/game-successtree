<?php

declare(strict_types=1);

namespace SuccessTree\Db;

use PDO;

/**
 * PDO adapter (sqlite / mysql). Handy for tests, demos, or hosts already using PDO.
 */
class PdoAdapter extends AbstractAdapter
{
    /** @var PDO */
    private $pdo;

    /** @var string */
    private $driver;

    public function __construct(PDO $pdo, ?string $driver = null)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($driver === null) {
            $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        }
        $this->driver = $driver === 'sqlite' ? 'sqlite' : 'mysql';
    }

    public function select(string $sql): array
    {
        $stmt = $this->pdo->query($sql);
        if ($stmt === false) {
            throw new \RuntimeException('Query failed');
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return is_array($rows) ? $rows : [];
    }

    public function exec(string $sql): void
    {
        if ($this->pdo->exec($sql) === false) {
            throw new \RuntimeException('Query failed');
        }
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    protected function quoteString(string $value): string
    {
        $q = $this->pdo->quote($value);
        if ($q === false) {
            return self::manualQuote($value, $this->driver);
        }
        return $q;
    }
}
