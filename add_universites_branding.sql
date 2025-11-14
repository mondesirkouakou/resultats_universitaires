-- Adds branding fields to universites

-- Idempotent: rely on importer to skip duplicate errors
ALTER TABLE `universites` ADD COLUMN `slogan` VARCHAR(255) NULL;
ALTER TABLE `universites` ADD COLUMN `logo_path` VARCHAR(255) NULL;
