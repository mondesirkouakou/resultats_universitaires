-- Script pour ajouter les tables de liaison pour les affectations
-- Exécutez ce script pour créer les tables manquantes

-- Table de liaison matière-professeur
DROP TABLE IF EXISTS `matiere_professeur`;
CREATE TABLE IF NOT EXISTS `matiere_professeur` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `matiere_id` int(11) NOT NULL,
  `professeur_id` int(11) NOT NULL,
  `date_affectation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `matiere_id` (`matiere_id`),
  KEY `professeur_id` (`professeur_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de liaison matière-filière
DROP TABLE IF EXISTS `matiere_filiere`;
CREATE TABLE IF NOT EXISTS `matiere_filiere` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `matiere_id` int(11) NOT NULL,
  `filiere_id` int(11) NOT NULL,
  `date_affectation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `matiere_id` (`matiere_id`),
  KEY `filiere_id` (`filiere_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de liaison professeur-classe
DROP TABLE IF EXISTS `professeur_classe`;
CREATE TABLE IF NOT EXISTS `professeur_classe` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `professeur_id` int(11) NOT NULL,
  `classe_id` int(11) NOT NULL,
  `date_affectation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `professeur_id` (`professeur_id`),
  KEY `classe_id` (`classe_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
