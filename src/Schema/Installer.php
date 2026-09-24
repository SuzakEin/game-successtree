<?php

declare(strict_types=1);

namespace SuccessTree\Schema;

use SuccessTree\Db\DbAdapterInterface;

/**
 * Creates the SuccessTree tables (CREATE TABLE IF NOT EXISTS + indexes) for mysql or sqlite.
 */
class Installer
{
    /** @var DbAdapterInterface */
    private $db;

    /** @var string */
    private $prefix;

    /** @var string */
    private $driver;

    public function __construct(DbAdapterInterface $db, string $prefix = 'st_', ?string $driver = null)
    {
        if (!preg_match('/^[a-zA-Z0-9_]*$/', $prefix)) {
            throw new \InvalidArgumentException('Invalid table prefix');
        }
        $this->db = $db;
        $this->prefix = $prefix;
        $d = $driver !== null && $driver !== '' ? $driver : $db->driver();
        $this->driver = $d === 'sqlite' ? 'sqlite' : 'mysql';
    }

    /** Runs every statement. Idempotent. */
    public function install(): void
    {
        foreach ($this->statements() as $sql) {
            $this->db->exec($sql);
        }
    }

    /** @return string[] */
    public function statements(): array
    {
        return $this->driver === 'sqlite' ? $this->sqlite() : $this->mysql();
    }

    /** @return string[] */
    private function mysql(): array
    {
        $p = $this->prefix;
        $tail = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        return [
            "CREATE TABLE IF NOT EXISTS `{$p}tree` (\n"
            . "  `uid` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,\n"
            . "  `slug` VARCHAR(120) NOT NULL,\n"
            . "  `name` VARCHAR(190) NOT NULL DEFAULT '',\n"
            . "  `description` TEXT NULL,\n"
            . "  `layout` VARCHAR(32) NOT NULL DEFAULT 'free',\n"
            . "  `theme` TEXT NULL,\n"
            . "  `settings` TEXT NULL,\n"
            . "  `created_at` DATETIME NULL,\n"
            . "  `updated_at` DATETIME NULL,\n"
            . "  PRIMARY KEY (`uid`),\n"
            . "  UNIQUE KEY `{$p}tree_slug` (`slug`)\n"
            . ")" . $tail,
            "CREATE TABLE IF NOT EXISTS `{$p}node` (\n"
            . "  `uid` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,\n"
            . "  `tree_uid` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,\n"
            . "  `parent_uid` VARCHAR(64) COLLATE utf8mb4_bin NULL,\n"
            . "  `kind` VARCHAR(16) NOT NULL DEFAULT 'node',\n"
            . "  `slug` VARCHAR(120) NULL,\n"
            . "  `name` VARCHAR(190) NOT NULL DEFAULT '',\n"
            . "  `description` TEXT NULL,\n"
            . "  `icon` VARCHAR(64) COLLATE utf8mb4_bin NULL,\n"
            . "  `pos_x` DOUBLE NULL,\n"
            . "  `pos_y` DOUBLE NULL,\n"
            . "  `theme` TEXT NULL,\n"
            . "  `reward` TEXT NULL,\n"
            . "  `sort` INT NOT NULL DEFAULT 0,\n"
            . "  `meta` TEXT NULL,\n"
            . "  PRIMARY KEY (`uid`),\n"
            . "  KEY `{$p}node_tree` (`tree_uid`),\n"
            . "  KEY `{$p}node_parent` (`parent_uid`)\n"
            . ")" . $tail,
            "CREATE TABLE IF NOT EXISTS `{$p}link` (\n"
            . "  `uid` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,\n"
            . "  `tree_uid` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,\n"
            . "  `from_uid` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,\n"
            . "  `to_uid` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,\n"
            . "  `type` VARCHAR(16) NOT NULL DEFAULT 'path',\n"
            . "  PRIMARY KEY (`uid`),\n"
            . "  KEY `{$p}link_tree` (`tree_uid`)\n"
            . ")" . $tail,
            "CREATE TABLE IF NOT EXISTS `{$p}condition` (\n"
            . "  `uid` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,\n"
            . "  `node_uid` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,\n"
            . "  `phase` VARCHAR(16) NOT NULL DEFAULT 'unlock',\n"
            . "  `type` VARCHAR(32) NOT NULL,\n"
            . "  `params` TEXT NULL,\n"
            . "  `sort` INT NOT NULL DEFAULT 0,\n"
            . "  PRIMARY KEY (`uid`),\n"
            . "  KEY `{$p}condition_node` (`node_uid`)\n"
            . ")" . $tail,
            "CREATE TABLE IF NOT EXISTS `{$p}progress` (\n"
            . "  `tree_uid` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,\n"
            . "  `node_uid` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,\n"
            . "  `subject` VARCHAR(128) NOT NULL,\n"
            . "  `status` VARCHAR(16) NOT NULL DEFAULT 'completed',\n"
            . "  `completed_at` DATETIME NULL,\n"
            . "  `data` TEXT NULL,\n"
            . "  PRIMARY KEY (`node_uid`, `subject`),\n"
            . "  KEY `{$p}progress_tree_subject` (`tree_uid`, `subject`)\n"
            . ")" . $tail,
            "CREATE TABLE IF NOT EXISTS `{$p}metric` (\n"
            . "  `subject` VARCHAR(128) NOT NULL,\n"
            . "  `metric` VARCHAR(120) NOT NULL,\n"
            . "  `value` DOUBLE NOT NULL DEFAULT 0,\n"
            . "  `updated_at` DATETIME NULL,\n"
            . "  PRIMARY KEY (`subject`, `metric`)\n"
            . ")" . $tail,
        ];
    }

    /** @return string[] */
    private function sqlite(): array
    {
        $p = $this->prefix;
        return [
            "CREATE TABLE IF NOT EXISTS \"{$p}tree\" (uid VARCHAR(64) NOT NULL PRIMARY KEY, slug VARCHAR(120) NOT NULL UNIQUE,"
            . " name VARCHAR(190) NOT NULL DEFAULT '', description TEXT NULL, layout VARCHAR(32) NOT NULL DEFAULT 'free',"
            . " theme TEXT NULL, settings TEXT NULL, created_at DATETIME NULL, updated_at DATETIME NULL)",
            "CREATE TABLE IF NOT EXISTS \"{$p}node\" (uid VARCHAR(64) NOT NULL PRIMARY KEY, tree_uid VARCHAR(64) NOT NULL,"
            . " parent_uid VARCHAR(64) NULL, kind VARCHAR(16) NOT NULL DEFAULT 'node', slug VARCHAR(120) NULL,"
            . " name VARCHAR(190) NOT NULL DEFAULT '', description TEXT NULL, icon VARCHAR(64) NULL, pos_x DOUBLE NULL,"
            . " pos_y DOUBLE NULL, theme TEXT NULL, reward TEXT NULL, sort INT NOT NULL DEFAULT 0, meta TEXT NULL)",
            "CREATE INDEX IF NOT EXISTS \"{$p}node_tree\" ON \"{$p}node\" (tree_uid)",
            "CREATE INDEX IF NOT EXISTS \"{$p}node_parent\" ON \"{$p}node\" (parent_uid)",
            "CREATE TABLE IF NOT EXISTS \"{$p}link\" (uid VARCHAR(64) NOT NULL PRIMARY KEY, tree_uid VARCHAR(64) NOT NULL,"
            . " from_uid VARCHAR(64) NOT NULL, to_uid VARCHAR(64) NOT NULL, type VARCHAR(16) NOT NULL DEFAULT 'path')",
            "CREATE INDEX IF NOT EXISTS \"{$p}link_tree\" ON \"{$p}link\" (tree_uid)",
            "CREATE TABLE IF NOT EXISTS \"{$p}condition\" (uid VARCHAR(64) NOT NULL PRIMARY KEY, node_uid VARCHAR(64) NOT NULL,"
            . " phase VARCHAR(16) NOT NULL DEFAULT 'unlock', type VARCHAR(32) NOT NULL, params TEXT NULL, sort INT NOT NULL DEFAULT 0)",
            "CREATE INDEX IF NOT EXISTS \"{$p}condition_node\" ON \"{$p}condition\" (node_uid)",
            "CREATE TABLE IF NOT EXISTS \"{$p}progress\" (tree_uid VARCHAR(64) NOT NULL, node_uid VARCHAR(64) NOT NULL,"
            . " subject VARCHAR(128) NOT NULL, status VARCHAR(16) NOT NULL DEFAULT 'completed', completed_at DATETIME NULL,"
            . " data TEXT NULL, PRIMARY KEY (node_uid, subject))",
            "CREATE INDEX IF NOT EXISTS \"{$p}progress_tree_subject\" ON \"{$p}progress\" (tree_uid, subject)",
            "CREATE TABLE IF NOT EXISTS \"{$p}metric\" (subject VARCHAR(128) NOT NULL, metric VARCHAR(120) NOT NULL,"
            . " value DOUBLE NOT NULL DEFAULT 0, updated_at DATETIME NULL, PRIMARY KEY (subject, metric))",
        ];
    }
}
