-- =====================================================
-- SCRIPT DE MIGRATION SÉCURISÉ
-- Système de Gestion Universitaire
-- =====================================================
-- Ce script vérifie l'existence des colonnes avant de les ajouter
-- =====================================================

-- Utilisation de procédures stockées pour vérifier l'existence des colonnes

DELIMITER $$

-- Procédure pour ajouter une colonne si elle n'existe pas
CREATE PROCEDURE AddColumnIfNotExists(
    IN tableName VARCHAR(100),
    IN columnName VARCHAR(100),
    IN columnDefinition TEXT
)
BEGIN
    DECLARE columnExists INT DEFAULT 0;
    
    SELECT COUNT(*) INTO columnExists
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = tableName
    AND COLUMN_NAME = columnName;
    
    IF columnExists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', tableName, '` ADD COLUMN `', columnName, '` ', columnDefinition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('Colonne ', columnName, ' ajoutée à ', tableName) as message;
    ELSE
        SELECT CONCAT('Colonne ', columnName, ' existe déjà dans ', tableName) as message;
    END IF;
END$$

-- Procédure pour ajouter un index si il n'existe pas
CREATE PROCEDURE AddIndexIfNotExists(
    IN tableName VARCHAR(100),
    IN indexName VARCHAR(100),
    IN columnName VARCHAR(100)
)
BEGIN
    DECLARE indexExists INT DEFAULT 0;
    
    SELECT COUNT(*) INTO indexExists
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = tableName
    AND INDEX_NAME = indexName;
    
    IF indexExists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', tableName, '` ADD KEY `', indexName, '` (`', columnName, '`)');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('Index ', indexName, ' ajouté à ', tableName) as message;
    ELSE
        SELECT CONCAT('Index ', indexName, ' existe déjà dans ', tableName) as message;
    END IF;
END$$

DELIMITER ;

-- =====================================================
-- APPLICATIONS DES MIGRATIONS
-- =====================================================

-- 1. TABLE CLASSES
CALL AddColumnIfNotExists('classes', 'capacite', 'int(11) DEFAULT 30');

-- Modifier le type de la colonne annee (toujours exécuté)
ALTER TABLE `classes` MODIFY COLUMN `annee` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL;

-- 2. TABLE PROFESSEURS
CALL AddColumnIfNotExists('professeurs', 'matiere_id', 'int(11) DEFAULT NULL');
CALL AddIndexIfNotExists('professeurs', 'matiere_id', 'matiere_id');

-- 3. CHAMPS DE CONNEXION - ÉTUDIANTS
CALL AddColumnIfNotExists('etudiants', 'mot_de_passe', 'varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL');
CALL AddColumnIfNotExists('etudiants', 'compte_actif', 'tinyint(1) DEFAULT 0');
CALL AddColumnIfNotExists('etudiants', 'premiere_connexion', 'tinyint(1) DEFAULT 1');
CALL AddColumnIfNotExists('etudiants', 'date_creation_compte', 'timestamp NULL DEFAULT NULL');
CALL AddColumnIfNotExists('etudiants', 'derniere_connexion', 'timestamp NULL DEFAULT NULL');

-- 4. CHAMPS DE CONNEXION - PROFESSEURS
CALL AddColumnIfNotExists('professeurs', 'mot_de_passe', 'varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL');
CALL AddColumnIfNotExists('professeurs', 'compte_actif', 'tinyint(1) DEFAULT 0');
CALL AddColumnIfNotExists('professeurs', 'premiere_connexion', 'tinyint(1) DEFAULT 1');
CALL AddColumnIfNotExists('professeurs', 'date_creation_compte', 'timestamp NULL DEFAULT NULL');
CALL AddColumnIfNotExists('professeurs', 'derniere_connexion', 'timestamp NULL DEFAULT NULL');

-- 5. INDEX UNIQUES POUR LES EMAILS
-- Ajouter l'index unique pour l'email des étudiants
CALL AddIndexIfNotExists('etudiants', 'email_unique_etudiants', 'email');

-- Ajouter l'index unique pour l'email des professeurs  
CALL AddIndexIfNotExists('professeurs', 'email_unique_professeurs', 'email');

-- 6. CRÉER LES TABLES DE LIAISON
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
-- NETTOYAGE
-- =====================================================

-- Supprimer les procédures temporaires
DROP PROCEDURE IF EXISTS AddColumnIfNotExists;
DROP PROCEDURE IF EXISTS AddIndexIfNotExists;

-- =====================================================
-- VÉRIFICATIONS FINALES
-- =====================================================

SELECT 'Migration terminée avec succès!' as message;

-- Vérifier les nouvelles colonnes
SELECT 
    TABLE_NAME, 
    COLUMN_NAME, 
    DATA_TYPE, 
    IS_NULLABLE, 
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME IN ('classes', 'professeurs', 'etudiants')
AND COLUMN_NAME IN ('capacite', 'matiere_id', 'mot_de_passe', 'compte_actif', 'premiere_connexion', 'date_creation_compte', 'derniere_connexion')
ORDER BY TABLE_NAME, COLUMN_NAME;

-- Vérifier les nouvelles tables
SELECT TABLE_NAME, TABLE_ROWS 
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME IN ('matiere_professeur', 'matiere_filiere', 'professeur_classe');
