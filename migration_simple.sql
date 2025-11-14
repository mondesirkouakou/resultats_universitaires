-- =====================================================
-- SCRIPT DE MIGRATION SIMPLE
-- Système de Gestion Universitaire
-- =====================================================
-- Exécutez ce script ligne par ligne ou section par section
-- Ignorez les erreurs "Duplicate column name" - c'est normal !
-- =====================================================

-- SECTION 1: TABLE CLASSES
-- Ajout du champ capacite (ignorez l'erreur si existe déjà)
ALTER TABLE `classes` ADD COLUMN `capacite` int(11) DEFAULT 30;

-- Modification du type de colonne annee (toujours nécessaire)
ALTER TABLE `classes` MODIFY COLUMN `annee` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL;

-- =====================================================

-- SECTION 2: TABLE PROFESSEURS - MATIERE
-- Ajout du champ matiere_id (ignorez l'erreur si existe déjà)
ALTER TABLE `professeurs` ADD COLUMN `matiere_id` int(11) DEFAULT NULL;

-- Ajout de l'index matiere_id (ignorez l'erreur si existe déjà)
ALTER TABLE `professeurs` ADD KEY `matiere_id` (`matiere_id`);

-- =====================================================

-- SECTION 3: ÉTUDIANTS - CHAMPS DE CONNEXION
-- Ajout des champs de connexion pour étudiants (ignorez les erreurs si existent déjà)
ALTER TABLE `etudiants` ADD COLUMN `mot_de_passe` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
ALTER TABLE `etudiants` ADD COLUMN `compte_actif` tinyint(1) DEFAULT 0;
ALTER TABLE `etudiants` ADD COLUMN `premiere_connexion` tinyint(1) DEFAULT 1;
ALTER TABLE `etudiants` ADD COLUMN `date_creation_compte` timestamp NULL DEFAULT NULL;
ALTER TABLE `etudiants` ADD COLUMN `derniere_connexion` timestamp NULL DEFAULT NULL;

-- Index unique pour email étudiants (ignorez l'erreur si existe déjà)
ALTER TABLE `etudiants` ADD UNIQUE KEY `email_unique_etudiants` (`email`);

-- =====================================================

-- SECTION 4: PROFESSEURS - CHAMPS DE CONNEXION
-- Ajout des champs de connexion pour professeurs (ignorez les erreurs si existent déjà)
ALTER TABLE `professeurs` ADD COLUMN `mot_de_passe` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
ALTER TABLE `professeurs` ADD COLUMN `compte_actif` tinyint(1) DEFAULT 0;
ALTER TABLE `professeurs` ADD COLUMN `premiere_connexion` tinyint(1) DEFAULT 1;
ALTER TABLE `professeurs` ADD COLUMN `date_creation_compte` timestamp NULL DEFAULT NULL;
ALTER TABLE `professeurs` ADD COLUMN `derniere_connexion` timestamp NULL DEFAULT NULL;

-- Index unique pour email professeurs (ignorez l'erreur si existe déjà)
ALTER TABLE `professeurs` ADD UNIQUE KEY `email_unique_professeurs` (`email`);

-- =====================================================

-- SECTION 5: TABLES DE LIAISON
-- Table matiere_professeur
CREATE TABLE IF NOT EXISTS `matiere_professeur` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `matiere_id` int(11) NOT NULL,
  `professeur_id` int(11) NOT NULL,
  `date_affectation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `matiere_id` (`matiere_id`),
  KEY `professeur_id` (`professeur_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table matiere_filiere
CREATE TABLE IF NOT EXISTS `matiere_filiere` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `matiere_id` int(11) NOT NULL,
  `filiere_id` int(11) NOT NULL,
  `date_affectation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `matiere_id` (`matiere_id`),
  KEY `filiere_id` (`filiere_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table professeur_classe
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
-- VÉRIFICATIONS (OPTIONNEL)
-- =====================================================

-- Vérifier que les colonnes ont été ajoutées
SHOW COLUMNS FROM `classes` LIKE 'capacite';
SHOW COLUMNS FROM `professeurs` LIKE 'matiere_id';
SHOW COLUMNS FROM `etudiants` LIKE 'mot_de_passe';
SHOW COLUMNS FROM `professeurs` LIKE 'mot_de_passe';

-- Vérifier que les tables ont été créées
SHOW TABLES LIKE 'matiere_%';
SHOW TABLES LIKE 'professeur_classe';

-- =====================================================
-- INSTRUCTIONS D'UTILISATION
-- =====================================================

/*
COMMENT UTILISER CE SCRIPT :

MÉTHODE 1 - Exécution complète :
1. Copiez tout le contenu de ce fichier
2. Collez-le dans phpMyAdmin > SQL
3. Cliquez sur "Exécuter"
4. IGNOREZ les erreurs "Duplicate column name" et "Duplicate key name"

MÉTHODE 2 - Exécution par sections :
1. Copiez une section à la fois (SECTION 1, puis SECTION 2, etc.)
2. Exécutez chaque section séparément
3. IGNOREZ les erreurs de doublons

ERREURS NORMALES À IGNORER :
- #1060 - Nom du champ 'capacite' déjà utilisé
- #1061 - Nom de clé 'matiere_id' déjà utilisé  
- #1062 - Entrée dupliquée pour la clé 'email_unique_etudiants'

CES ERREURS SIGNIFIENT QUE LA MODIFICATION A DÉJÀ ÉTÉ APPLIQUÉE !

APRÈS LA MIGRATION :
1. Testez admin/etudiants.php - bouton "Créer compte" doit apparaître
2. Testez admin/professeurs.php - bouton "Créer compte" doit apparaître  
3. Testez admin/affectations.php - plus d'erreur "récupération des données"
4. Testez student_login.php pour la connexion étudiants/professeurs
*/
