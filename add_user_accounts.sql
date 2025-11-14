-- Script pour ajouter les champs de connexion aux tables etudiants et professeurs
-- Exécutez ce script pour permettre la connexion des étudiants et professeurs

-- Ajouter les champs de connexion à la table etudiants
ALTER TABLE `etudiants` ADD COLUMN `mot_de_passe` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
ALTER TABLE `etudiants` ADD COLUMN `compte_actif` tinyint(1) DEFAULT 0;
ALTER TABLE `etudiants` ADD COLUMN `premiere_connexion` tinyint(1) DEFAULT 1;
ALTER TABLE `etudiants` ADD COLUMN `date_creation_compte` timestamp NULL DEFAULT NULL;
ALTER TABLE `etudiants` ADD COLUMN `derniere_connexion` timestamp NULL DEFAULT NULL;

-- Ajouter les champs de connexion à la table professeurs
ALTER TABLE `professeurs` ADD COLUMN `mot_de_passe` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
ALTER TABLE `professeurs` ADD COLUMN `compte_actif` tinyint(1) DEFAULT 0;
ALTER TABLE `professeurs` ADD COLUMN `premiere_connexion` tinyint(1) DEFAULT 1;
ALTER TABLE `professeurs` ADD COLUMN `date_creation_compte` timestamp NULL DEFAULT NULL;
ALTER TABLE `professeurs` ADD COLUMN `derniere_connexion` timestamp NULL DEFAULT NULL;

-- Rendre l'email unique pour les étudiants et professeurs
ALTER TABLE `etudiants` ADD UNIQUE KEY `email_unique` (`email`);
ALTER TABLE `professeurs` ADD UNIQUE KEY `email_unique` (`email`);
