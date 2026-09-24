<?php

declare(strict_types=1);

namespace SuccessTree\Db;

/**
 * Wraps a host "home-made singleton" exposing forceRequest(string $sql).
 *
 *   new ForceRequestAdapter(Database::getInstance(), [
 *       'method' => 'forceRequest',   // name of the host method
 *       'escape' => null,             // optional callable(string $raw): string (escaped, WITHOUT quotes)
 *       'driver' => 'mysql',          // mysql|sqlite (auto-detected when the host exposes a PDO)
 *   ]);
 *
 * Return values of forceRequest are normalised: array of rows (assoc arrays or stdClass),
 * a single assoc row, mysqli_result, PDOStatement, any Traversable, bool/int/null (writes).
 */
class ForceRequestAdapter extends AbstractAdapter
{
    /** @var object */
    private $host;

    /** @var string */
    private $method;

    /** @var callable|null */
    private $escape;

    /** @var \mysqli|null */
    private $mysqli;

    /** @var \PDO|null */
    private $pdo;

    /** @var string */
    private $driver;

    /**
     * @param object $host
     */
    public function __construct($host, array $options = [])
    {
        if (!is_object($host)) {
            throw new \InvalidArgumentException('ForceRequestAdapter expects an object');
        }
        $this->host = $host;
        $this->method = isset($options['method']) && is_string($options['method']) ? $options['method'] : 'forceRequest';
        if (!is_callable([$host, $this->method])) {
            throw new \InvalidArgumentException('Host object has no callable method ' . $this->method . '()');
        }
        $this->escape = isset($options['escape']) && is_callable($options['escape']) ? $options['escape'] : null;
        if ($this->escape === null) {
            $this->detectConnection();
        }
        $driver = isset($options['driver']) ? (string)$options['driver'] : '';
        if ($driver === '' && $this->pdo !== null) {
            $driver = (string)$this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        }
        $this->driver = $driver === 'sqlite' ? 'sqlite' : 'mysql';
    }

    public function select(string $sql): array
    {
        return self::normalizeRows($this->call($sql));
    }

    public function exec(string $sql): void
    {
        $result = $this->call($sql);
        if ($result === false) {
            throw new \RuntimeException('Write query failed');
        }
        if ($result instanceof \PDOStatement) {
            $result->closeCursor();
        } elseif ($result instanceof \mysqli_result) {
            $result->free();
        }
    }

    public function driver(): string
    {
        return $this->driver;
    }

    /** Which escaping strategy is active: callable|mysqli|pdo|manual (useful for diagnostics/tests). */
    public function escapeMode(): string
    {
        if ($this->escape !== null) {
            return 'callable';
        }
        if ($this->mysqli !== null) {
            return 'mysqli';
        }
        if ($this->pdo !== null) {
            return 'pdo';
        }
        return 'manual';
    }

    protected function quoteString(string $value): string
    {
        if ($this->escape !== null) {
            return "'" . (string)call_user_func($this->escape, $value) . "'";
        }
        if ($this->mysqli !== null) {
            return "'" . $this->mysqli->real_escape_string($value) . "'";
        }
        if ($this->pdo !== null) {
            $q = $this->pdo->quote($value);
            if ($q !== false) {
                return $q;
            }
        }
        return self::manualQuote($value, $this->driver);
    }

    /**
     * @return mixed
     */
    private function call(string $sql)
    {
        try {
            return call_user_func([$this->host, $this->method], $sql);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Host query failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private function detectConnection(): void
    {
        $candidates = [];
        foreach (['getConnection', 'getMysqli', 'getLink', 'getPdo', 'getDb'] as $m) {
            if (method_exists($this->host, $m) && is_callable([$this->host, $m])) {
                try {
                    $candidates[] = call_user_func([$this->host, $m]);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }
        $public = get_object_vars($this->host); // from this scope: public properties only
        foreach (['mysqli', 'connection', 'link', 'db', 'pdo'] as $p) {
            if (array_key_exists($p, $public)) {
                $candidates[] = $public[$p];
            }
        }
        foreach ($candidates as $c) {
            if ($c instanceof \mysqli) {
                $this->mysqli = $c;
                return;
            }
            if ($c instanceof \PDO) {
                $this->pdo = $c;
                return;
            }
        }
    }

    /**
     * @param mixed $result
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeRows($result): array
    {
        if ($result === null || is_bool($result) || is_int($result) || is_float($result) || is_string($result)) {
            return [];
        }
        if ($result instanceof \PDOStatement) {
            $rows = $result->fetchAll(\PDO::FETCH_ASSOC);
            $result->closeCursor();
            return is_array($rows) ? $rows : [];
        }
        if ($result instanceof \mysqli_result) {
            $rows = [];
            while (($row = $result->fetch_assoc()) !== null && $row !== false) {
                $rows[] = $row;
            }
            $result->free();
            return $rows;
        }
        if (is_array($result)) {
            if ($result === []) {
                return [];
            }
            $first = reset($result);
            if (!is_array($first) && !is_object($first)) {
                // a single associative row
                return [$result];
            }
            $rows = [];
            foreach ($result as $row) {
                $rows[] = self::rowToArray($row);
            }
            return $rows;
        }
        if ($result instanceof \Traversable) {
            $rows = [];
            foreach ($result as $row) {
                $rows[] = self::rowToArray($row);
            }
            return $rows;
        }
        if (is_object($result)) {
            return [self::rowToArray($result)];
        }
        return [];
    }

    /**
     * @param mixed $row
     */
    private static function rowToArray($row): array
    {
        if (is_array($row)) {
            return $row;
        }
        if (is_object($row)) {
            return get_object_vars($row);
        }
        return [];
    }
}
