# Système de Gestion des Comptes Utilisateurs

## Vue d'ensemble

Le système universitaire dispose maintenant d'un système complet de gestion des comptes pour les étudiants et professeurs, leur permettant de se connecter et d'accéder à leurs informations personnelles.

## 🚀 Installation et Configuration

### 1. Appliquer les modifications à la base de données

Exécutez le script de migration complet :

```sql
-- Dans phpMyAdmin ou votre client MySQL
SOURCE migration_complete.sql;
```

Ou exécutez les scripts individuels dans cet ordre :
1. `add_capacite_field.sql`
2. `update_professeurs_table.sql`
3. `add_user_accounts.sql`
4. `add_affectations_tables.sql`

### 2. Vérifier les fichiers créés

Les nouveaux fichiers suivants ont été ajoutés :
- `includes/user_accounts.php` - Fonctions de gestion des comptes
- `student_login.php` - Page de connexion étudiants/professeurs
- `change_password.php` - Changement de mot de passe obligatoire

## 👥 Utilisation du Système

### Pour les Administrateurs

#### Créer un compte étudiant :
1. Aller sur `admin/etudiants.php`
2. Créer un étudiant avec une **adresse email valide**
3. Cliquer sur le bouton vert "👤+" dans la colonne "Actions"
4. **Noter le mot de passe temporaire affiché** et le communiquer à l'étudiant

#### Créer un compte professeur :
1. Aller sur `admin/professeurs.php`
2. Créer un professeur avec une **adresse email valide**
3. Cliquer sur le bouton vert "👤+" dans la colonne "Actions"
4. **Noter le mot de passe temporaire affiché** et le communiquer au professeur

### Pour les Étudiants et Professeurs

#### Première connexion :
1. Aller sur `student_login.php`
2. Se connecter avec l'email et le mot de passe temporaire
3. **Obligatoire** : Changer le mot de passe sur `change_password.php`
4. Redirection automatique vers le dashboard approprié

#### Connexions suivantes :
1. Aller sur `student_login.php`
2. Se connecter avec l'email et le nouveau mot de passe
3. Accès direct au dashboard

## 🔐 Sécurité

### Mots de passe
- **Génération automatique** : 10 caractères avec lettres, chiffres et symboles
- **Hashage sécurisé** : Utilisation de `password_hash()` PHP
- **Changement obligatoire** : À la première connexion
- **Validation** : Minimum 8 caractères pour les nouveaux mots de passe

### Sessions
- **Séparation des accès** : Admin, étudiants et professeurs ont des sessions distinctes
- **Vérification des permissions** : Contrôle d'accès sur chaque page
- **Déconnexion sécurisée** : Destruction complète des sessions

## 📊 Interface d'Administration

### Colonne "Compte" dans les tableaux
- **🟢 Actif** : L'utilisateur a un compte créé
- **🟡 Aucun** : Pas de compte créé

### Boutons d'action
- **✏️ Éditer** : Modifier les informations
- **👤+ Créer compte** : Visible seulement si pas de compte et email présent
- **🗑️ Supprimer** : Supprimer l'utilisateur

## 🔧 Fonctionnalités Techniques

### Fonctions principales (`includes/user_accounts.php`)

```php
// Générer un mot de passe sécurisé
generatePassword($length = 8)

// Créer un compte étudiant
createStudentAccount($pdo, $etudiant_id)

// Créer un compte professeur
createProfessorAccount($pdo, $professeur_id)

// Authentifier un utilisateur
authenticateUser($pdo, $email, $password)

// Changer le mot de passe
changePassword($pdo, $user_id, $user_type, $new_password)
```

### Structure de base de données

#### Tables modifiées :
- `etudiants` : Ajout des champs de connexion
- `professeurs` : Ajout des champs de connexion + `matiere_id`
- `classes` : Ajout du champ `capacite`

#### Nouvelles tables de liaison :
- `matiere_professeur` : Liaison matières ↔ professeurs
- `matiere_filiere` : Liaison matières ↔ filières
- `professeur_classe` : Liaison professeurs ↔ classes

## 🐛 Dépannage

### Problèmes courants

#### "Erreur lors de la récupération des données" sur affectations.php
✅ **Résolu** : Correction des requêtes SQL pour utiliser les nouveaux champs

#### Bouton "Créer compte" n'apparaît pas
- Vérifier que l'étudiant/professeur a une adresse email
- Vérifier qu'il n'a pas déjà un compte

#### Impossible de se connecter
- Vérifier que le compte est actif (`compte_actif = 1`)
- Vérifier l'adresse email
- Réinitialiser le mot de passe si nécessaire

### Logs et débogage

Pour déboguer les problèmes de connexion, vérifiez :
```sql
-- Vérifier les comptes étudiants
SELECT id, nom, prenom, email, compte_actif, premiere_connexion 
FROM etudiants WHERE email = 'email@exemple.com';

-- Vérifier les comptes professeurs
SELECT id, nom, prenom, email, compte_actif, premiere_connexion 
FROM professeurs WHERE email = 'email@exemple.com';
```

## 📈 Évolutions Futures

### Fonctionnalités prévues :
- Dashboard étudiant avec notes et emploi du temps
- Dashboard professeur avec gestion des classes
- Système de notifications
- Réinitialisation de mot de passe par email
- Gestion des rôles avancée

## 📞 Support

En cas de problème :
1. Vérifier que tous les scripts SQL ont été exécutés
2. Contrôler les permissions de fichiers
3. Vérifier les logs d'erreur PHP
4. Tester avec un compte de test

---

**Date de création** : Janvier 2025  
**Version** : 1.0  
**Compatibilité** : PHP 7.4+, MySQL 5.7+
