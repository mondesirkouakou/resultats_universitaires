-- Migration: add `code` and `date_creation` to `universites` with unique index on `code`.

-- Add columns (idempotent via installer)
ALTER TABLE `universites` ADD COLUMN `code` varchar(50) NULL AFTER `nom`;
ALTER TABLE `universites` ADD COLUMN `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP AFTER `site_web`;

-- Add unique index (allows multiple NULLs by default in MySQL)
CREATE UNIQUE INDEX `uniq_universites_code` ON `universites` (`code`);
