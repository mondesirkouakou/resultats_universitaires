-- =====================================================
-- SCRIPT DE MIGRATION COMPLET
-- Système de Gestion Universitaire
-- =====================================================
-- Ce script applique tous les changements nécessaires
-- pour mettre à jour une base de données existante
-- =====================================================

-- 1. MISE À JOUR DE LA TABLE CLASSES
-- Ajouter le champ capacite et modifier le type de annee
-- (Ignorez l'erreur si la colonne existe déjà)
ALTER TABLE `classes` ADD COLUMN `capacite` int(11) DEFAULT 30;
ALTER TABLE `classes` MODIFY COLUMN `annee` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL;

-- 2. MISE À JOUR DE LA TABLE PROFESSEURS
-- Ajouter matiere_id
-- (Ignorez l'erreur si la colonne existe déjà)
ALTER TABLE `professeurs` ADD COLUMN `matiere_id` int(11) DEFAULT NULL;
ALTER TABLE `professeurs` ADD KEY `matiere_id` (`matiere_id`);

-- Supprimer les anciennes colonnes (décommentez si vous voulez les supprimer définitivement)
-- ALTER TABLE `professeurs` DROP INDEX `ufr_id`;
-- ALTER TABLE `professeurs` DROP COLUMN `ufr_id`;
-- ALTER TABLE `professeurs` DROP COLUMN `specialite`;

-- 3. AJOUTER LES CHAMPS DE CONNEXION AUX ÉTUDIANTS
-- (Ignorez les erreurs si les colonnes existent déjà)
ALTER TABLE `etudiants` ADD COLUMN `mot_de_passe` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
ALTER TABLE `etudiants` ADD COLUMN `compte_actif` tinyint(1) DEFAULT 0;
ALTER TABLE `etudiants` ADD COLUMN `premiere_connexion` tinyint(1) DEFAULT 1;
ALTER TABLE `etudiants` ADD COLUMN `date_creation_compte` timestamp NULL DEFAULT NULL;
ALTER TABLE `etudiants` ADD COLUMN `derniere_connexion` timestamp NULL DEFAULT NULL;

-- Ajouter l'index unique pour l'email des étudiants (ignorez l'erreur si existe déjà)
ALTER TABLE `etudiants` ADD UNIQUE KEY `email_unique_etudiants` (`email`);

-- 4. AJOUTER LES CHAMPS DE CONNEXION AUX PROFESSEURS
-- (Ignorez les erreurs si les colonnes existent déjà)
ALTER TABLE `professeurs` ADD COLUMN `mot_de_passe` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
ALTER TABLE `professeurs` ADD COLUMN `compte_actif` tinyint(1) DEFAULT 0;
ALTER TABLE `professeurs` ADD COLUMN `premiere_connexion` tinyint(1) DEFAULT 1;
ALTER TABLE `professeurs` ADD COLUMN `date_creation_compte` timestamp NULL DEFAULT NULL;
ALTER TABLE `professeurs` ADD COLUMN `derniere_connexion` timestamp NULL DEFAULT NULL;

-- Ajouter l'index unique pour l'email des professeurs (ignorez l'erreur si existe déjà)
ALTER TABLE `professeurs` ADD UNIQUE KEY `email_unique_professeurs` (`email`);

-- 5. CRÉER LES TABLES DE LIAISON POUR LES AFFECTATIONS
-- Table de liaison matière-professeur
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
CREATE TABLE IF NOT EXISTS `professeur_classe` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `professeur_id` int(11) NOT NULL,
  `classe_id` int(11) NOT NULL,
  `date_affectation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `professeur_id` (`professeur_id`),
  KEY `classe_id` (`classe_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- VÉRIFICATIONS ET INFORMATIONS
-- =====================================================

-- Afficher les informations sur les tables modifiées
SELECT 'Migration terminée avec succès!' as message;

-- Vérifier la structure des tables principales
DESCRIBE `classes`;
DESCRIBE `professeurs`;
DESCRIBE `etudiants`;

-- Compter les enregistrements dans les nouvelles tables
SELECT COUNT(*) as nb_matiere_professeur FROM `matiere_professeur`;
SELECT COUNT(*) as nb_matiere_filiere FROM `matiere_filiere`;
SELECT COUNT(*) as nb_professeur_classe FROM `professeur_classe`;

-- =====================================================
-- NOTES IMPORTANTES
-- =====================================================
/*
APRÈS AVOIR EXÉCUTÉ CE SCRIPT :

1. Vérifiez que toutes les modifications ont été appliquées
2. Testez la création de comptes étudiants/professeurs
3. Testez les affectations dans admin/affectations.php
4. Vérifiez que les pages de connexion fonctionnent

FICHIERS CRÉÉS/MODIFIÉS :
- includes/user_accounts.php (fonctions de gestion des comptes)
- student_login.php (page de connexion étudiants/professeurs)
- change_password.php (changement de mot de passe)
- admin/etudiants.php (ajout création de comptes)
- admin/professeurs.php (ajout création de comptes)
- admin/affectations.php (correction des erreurs SQL)

PROCESSUS DE CRÉATION DE COMPTE :
1. Admin crée étudiant/professeur avec email
2. Admin clique "Créer un compte" → mot de passe généré
3. Utilisateur se connecte sur student_login.php
4. Première connexion → change_password.php
5. Redirection vers dashboard approprié
*/
