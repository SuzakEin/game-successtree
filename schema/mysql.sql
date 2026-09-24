-- SuccessTree schema (MySQL / MariaDB, utf8mb4). Default table prefix: st_
-- Same DDL as SuccessTree\Schema\Installer (driver mysql). Replace st_ if you use another prefix.

CREATE TABLE IF NOT EXISTS `st_tree` (
  `uid` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `name` VARCHAR(190) NOT NULL DEFAULT '',
  `description` TEXT NULL,
  `layout` VARCHAR(32) NOT NULL DEFAULT 'free',
  `theme` TEXT NULL,
  `settings` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`uid`),
  UNIQUE KEY `st_tree_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `st_node` (
  `uid` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,
  `tree_uid` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,
  `parent_uid` VARCHAR(64) COLLATE utf8mb4_bin NULL,
  `kind` VARCHAR(16) NOT NULL DEFAULT 'node',
  `slug` VARCHAR(120) NULL,
  `name` VARCHAR(190) NOT NULL DEFAULT '',
  `description` TEXT NULL,
  `icon` VARCHAR(64) COLLATE utf8mb4_bin NULL,
  `pos_x` DOUBLE NULL,
  `pos_y` DOUBLE NULL,
  `theme` TEXT NULL,
  `reward` TEXT NULL,
  `sort` INT NOT NULL DEFAULT 0,
  `meta` TEXT NULL,
  PRIMARY KEY (`uid`),
  KEY `st_node_tree` (`tree_uid`),
  KEY `st_node_parent` (`parent_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `st_link` (
  `uid` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,
  `tree_uid` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,
  `from_uid` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,
  `to_uid` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,
  `type` VARCHAR(16) NOT NULL DEFAULT 'path',
  PRIMARY KEY (`uid`),
  KEY `st_link_tree` (`tree_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `st_condition` (
  `uid` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,
  `node_uid` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,
  `phase` VARCHAR(16) NOT NULL DEFAULT 'unlock',
  `type` VARCHAR(32) NOT NULL,
  `params` TEXT NULL,
  `sort` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`uid`),
  KEY `st_condition_node` (`node_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `st_progress` (
  `tree_uid` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,
  `node_uid` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,
  `subject` VARCHAR(128) NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'completed',
  `completed_at` DATETIME NULL,
  `data` TEXT NULL,
  PRIMARY KEY (`node_uid`, `subject`),
  KEY `st_progress_tree_subject` (`tree_uid`, `subject`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `st_metric` (
  `subject` VARCHAR(128) NOT NULL,
  `metric` VARCHAR(120) NOT NULL,
  `value` DOUBLE NOT NULL DEFAULT 0,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`subject`, `metric`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

