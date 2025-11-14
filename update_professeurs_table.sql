-- Script pour mettre à jour la table professeurs existante
-- Exécutez ce script si la table professeurs existe déjà dans votre base de données

-- Ajouter la colonne matiere_id
ALTER TABLE `professeurs` ADD COLUMN `matiere_id` int(11) DEFAULT NULL;

-- Ajouter l'index pour matiere_id
ALTER TABLE `professeurs` ADD KEY `matiere_id` (`matiere_id`);

-- Supprimer la colonne specialite (optionnel - gardez les données si nécessaire)
-- ALTER TABLE `professeurs` DROP COLUMN `specialite`;

-- Supprimer la colonne ufr_id et son index
ALTER TABLE `professeurs` DROP INDEX `ufr_id`;
ALTER TABLE `professeurs` DROP COLUMN `ufr_id`;
