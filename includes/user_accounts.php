<?php
/**
 * Fonctions pour la gestion des comptes utilisateurs (étudiants et professeurs)
 */

/**
 * Génère un mot de passe aléatoire sécurisé
 * @param int $length Longueur du mot de passe (défaut: 8)
 * @return string Mot de passe généré
 */
function generatePassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    $password = '';
    $charLength = strlen($chars);
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $charLength - 1)];
    }
    
    return $password;
}

/**
 * Crée un compte pour une université
 * @param PDO $pdo Connexion à la base de données
 * @param int $universite_id ID de l'université
 * @return array Résultat avec succès et mot de passe généré
 */
function createUniversityAccount($pdo, $universite_id) {
    try {
        // Vérifier si l'université existe et récupérer ses informations
        $stmt = $pdo->prepare("SELECT id, nom, email, mot_de_passe FROM universites WHERE id = ?");
        $stmt->execute([$universite_id]);
        $univ = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$univ) {
            return ['success' => false, 'message' => "Université introuvable"];
        }

        if (empty($univ['email'])) {
            return ['success' => false, 'message' => "L'université doit avoir une adresse email"];
        }

        if (!empty($univ['mot_de_passe'])) {
            return ['success' => false, 'message' => 'Un compte existe déjà pour cette université'];
        }

        $password = generatePassword(10);
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE universites SET mot_de_passe = ?, compte_actif = 1, premiere_connexion = 1, date_creation_compte = NOW() WHERE id = ?");
        $stmt->execute([$hashedPassword, $universite_id]);

        return [
            'success' => true,
            'password' => $password,
            'email' => $univ['email'],
            'nom' => $univ['nom']
        ];

    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur lors de la création du compte: ' . $e->getMessage()];
    }
}

/**
 * Crée un compte pour un étudiant
 * @param PDO $pdo Connexion à la base de données
 * @param int $etudiant_id ID de l'étudiant
 * @return array Résultat avec succès et mot de passe généré
 */
function createStudentAccount($pdo, $etudiant_id) {
    try {
        // Vérifier si l'étudiant existe et récupérer ses informations
        $stmt = $pdo->prepare("SELECT id, nom, prenom, email FROM etudiants WHERE id = ?");
        $stmt->execute([$etudiant_id]);
        $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$etudiant) {
            return ['success' => false, 'message' => 'Étudiant introuvable'];
        }
        
        if (empty($etudiant['email'])) {
            return ['success' => false, 'message' => 'L\'étudiant doit avoir une adresse email'];
        }
        
        // Vérifier si un compte existe déjà
        if (!empty($etudiant['mot_de_passe'])) {
            return ['success' => false, 'message' => 'Un compte existe déjà pour cet étudiant'];
        }
        
        // Générer un mot de passe
        $password = generatePassword(10);
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Créer le compte
        $stmt = $pdo->prepare("
            UPDATE etudiants 
            SET mot_de_passe = ?, compte_actif = 1, premiere_connexion = 1, date_creation_compte = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$hashedPassword, $etudiant_id]);
        
        return [
            'success' => true, 
            'password' => $password,
            'email' => $etudiant['email'],
            'nom' => $etudiant['nom'],
            'prenom' => $etudiant['prenom']
        ];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur lors de la création du compte: ' . $e->getMessage()];
    }
}

/**
 * Crée un compte pour un professeur
 * @param PDO $pdo Connexion à la base de données
 * @param int $professeur_id ID du professeur
 * @return array Résultat avec succès et mot de passe généré
 */
function createProfessorAccount($pdo, $professeur_id) {
    try {
        // Vérifier si le professeur existe et récupérer ses informations
        $stmt = $pdo->prepare("SELECT id, nom, prenom, email FROM professeurs WHERE id = ?");
        $stmt->execute([$professeur_id]);
        $professeur = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$professeur) {
            return ['success' => false, 'message' => 'Professeur introuvable'];
        }
        
        if (empty($professeur['email'])) {
            return ['success' => false, 'message' => 'Le professeur doit avoir une adresse email'];
        }
        
        // Vérifier si un compte existe déjà
        if (!empty($professeur['mot_de_passe'])) {
            return ['success' => false, 'message' => 'Un compte existe déjà pour ce professeur'];
        }
        
        // Générer un mot de passe
        $password = generatePassword(10);
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Créer le compte
        $stmt = $pdo->prepare("
            UPDATE professeurs 
            SET mot_de_passe = ?, compte_actif = 1, premiere_connexion = 1, date_creation_compte = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$hashedPassword, $professeur_id]);
        
        return [
            'success' => true, 
            'password' => $password,
            'email' => $professeur['email'],
            'nom' => $professeur['nom'],
            'prenom' => $professeur['prenom']
        ];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur lors de la création du compte: ' . $e->getMessage()];
    }
}

/**
 * Authentifie un étudiant ou un professeur
 * @param PDO $pdo Connexion à la base de données
 * @param string $email Email de l'utilisateur
 * @param string $password Mot de passe
 * @return array Résultat de l'authentification
 */
function authenticateUser($pdo, $email, $password) {
    try {
        // Chercher d'abord dans les étudiants
        $stmt = $pdo->prepare("
            SELECT id, nom, prenom, email, mot_de_passe, compte_actif, premiere_connexion, 'etudiant' as type 
            FROM etudiants 
            WHERE email = ? AND compte_actif = 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si pas trouvé dans les étudiants, chercher dans les professeurs
        if (!$user) {
            $stmt = $pdo->prepare("
                SELECT id, nom, prenom, email, mot_de_passe, compte_actif, premiere_connexion, 'professeur' as type 
                FROM professeurs 
                WHERE email = ? AND compte_actif = 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$user) {
            return ['success' => false, 'message' => 'Email ou mot de passe incorrect'];
        }
        
        // Vérifier le mot de passe
        if (!password_verify($password, $user['mot_de_passe'])) {
            return ['success' => false, 'message' => 'Email ou mot de passe incorrect'];
        }
        
        // Mettre à jour la dernière connexion
        $table = $user['type'] === 'etudiant' ? 'etudiants' : 'professeurs';
        $stmt = $pdo->prepare("UPDATE {$table} SET derniere_connexion = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        return [
            'success' => true,
            'user_id' => $user['id'],
            'email' => $user['email'],
            'user_data' => [
                'id' => $user['id'],
                'nom' => $user['nom'],
                'prenom' => $user['prenom'],
                'email' => $user['email'],
                'type' => $user['type'],
                'premiere_connexion' => (bool)$user['premiere_connexion']
            ]
        ];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur lors de l\'authentification'];
    }
}

/**
 * Authentifie une université
 * @param PDO $pdo
 * @param string $email
 * @param string $password
 * @return array
 */
function authenticateUniversite($pdo, $email, $password) {
    try {
        $stmt = $pdo->prepare("SELECT id, nom, email, mot_de_passe, compte_actif, premiere_connexion FROM universites WHERE email = ? AND compte_actif = 1");
        $stmt->execute([$email]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$u) {
            return ['success' => false, 'message' => 'Email ou mot de passe incorrect'];
        }

        if (!password_verify($password, $u['mot_de_passe'])) {
            return ['success' => false, 'message' => 'Email ou mot de passe incorrect'];
        }

        $upd = $pdo->prepare("UPDATE universites SET derniere_connexion = NOW() WHERE id = ?");
        $upd->execute([$u['id']]);

        return [
            'success' => true,
            'user_id' => $u['id'],
            'email' => $u['email'],
            'user_data' => [
                'id' => $u['id'],
                'nom' => $u['nom'],
                'prenom' => '',
                'email' => $u['email'],
                'type' => 'universite',
                'premiere_connexion' => (bool)$u['premiere_connexion']
            ]
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => "Erreur lors de l'authentification"];
    }
}

/**
 * Change le mot de passe d'un utilisateur
 * @param PDO $pdo Connexion à la base de données
 * @param int $user_id ID de l'utilisateur
 * @param string $user_type Type d'utilisateur ('etudiant' ou 'professeur')
 * @param string $new_password Nouveau mot de passe
 * @return array Résultat de l'opération
 */
function changePassword($pdo, $user_id, $user_type, $new_password) {
    try {
        $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
        switch ($user_type) {
            case 'etudiant':
                $table = 'etudiants';
                break;
            case 'professeur':
                $table = 'professeurs';
                break;
            case 'universite':
                $table = 'universites';
                break;
            default:
                return ['success' => false, 'message' => "Type d'utilisateur invalide"];
        }

        $stmt = $pdo->prepare("UPDATE {$table} SET mot_de_passe = ?, premiere_connexion = 0 WHERE id = ?");
        $stmt->execute([$hashedPassword, $user_id]);

        return ['success' => true, 'message' => 'Mot de passe modifié avec succès'];

    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur lors de la modification du mot de passe'];
    }
}
?>
