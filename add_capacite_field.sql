-- Script pour mettre à jour la table classes existante
-- Exécutez ce script si la table classes existe déjà dans votre base de données

-- Modifier le type de la colonne annee pour accepter les années académiques (ex: 2024-2025)
ALTER TABLE `classes` MODIFY COLUMN `annee` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL;

-- Ajouter la colonne capacite si elle n'existe pas déjà
ALTER TABLE `classes` ADD COLUMN `capacite` int(11) DEFAULT 30;

-- Mettre à jour les classes existantes avec une capacité par défaut si nécessaire
UPDATE `classes` SET `capacite` = 30 WHERE `capacite` IS NULL;
