<?php

declare(strict_types=1);

namespace SuccessTree\Db;

/**
 * Minimal database contract used by SuccessTree.
 * Every value MUST go through quote() before being concatenated into SQL.
 */
interface DbAdapterInterface
{
    /**
     * Run a read query and return associative rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public function select(string $sql): array;

    /** Run a write query (INSERT/UPDATE/DELETE/DDL). Throws on failure. */
    public function exec(string $sql): void;

    /**
     * Safe SQL literal for NULL / int / float / bool / string (arrays & objects are JSON-encoded).
     *
     * @param mixed $value
     */
    public function quote($value): string;

    /** "mysql" or "sqlite". */
    public function driver(): string;
}
