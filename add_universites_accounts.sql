-- Migration: add account fields for universites to allow real login

ALTER TABLE `universites`
  ADD COLUMN `mot_de_passe` varchar(255) NULL AFTER `email`,
  ADD COLUMN `compte_actif` tinyint(1) DEFAULT 0 AFTER `mot_de_passe`,
  ADD COLUMN `premiere_connexion` tinyint(1) DEFAULT 1 AFTER `compte_actif`,
  ADD COLUMN `date_creation_compte` timestamp NULL DEFAULT NULL AFTER `premiere_connexion`,
  ADD COLUMN `derniere_connexion` timestamp NULL DEFAULT NULL AFTER `date_creation_compte`;

-- Ensure email is unique if provided
-- With utf8mb4 on MyISAM, indexes are limited by bytes. Use 191 chars (191*4=764 bytes) to stay under the limit.
ALTER TABLE `universites`
  MODIFY COLUMN `email` varchar(191) NULL;

ALTER TABLE `universites`
  ADD UNIQUE KEY `email_unique_universites` (`email`);
