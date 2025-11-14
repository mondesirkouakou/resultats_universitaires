# Portail des Résultats Universitaires

Une application web moderne pour la gestion et la consultation des résultats universitaires, développée avec PHP, HTML, Bootstrap et JavaScript.

## 🎯 Fonctionnalités

### Pour les Étudiants
- Consultation des résultats en temps réel
- Visualisation des notes par matière
- Suivi de la progression académique
- Accès aux évaluations détaillées
- Interface moderne et responsive

### Pour les Professeurs
- Saisie des notes et évaluations
- Gestion des matières enseignées
- Statistiques de performance
- Suivi des étudiants

### Pour l'Administrateur Principal
- Gestion des universités
- Supervision globale du système
- Statistiques globales
- Configuration système
- Gestion des comptes administrateurs

### Pour les Universités (Administrateurs Universitaires)
- Gestion des filières
- Gestion des matières
- Gestion des classes
- Gestion des étudiants
- Gestion des professeurs
- Gestion des affectations (étudiants aux classes, matières aux professeurs, etc.)
- Statistiques de l'université

### Pour les Parents
- Suivi des résultats de leurs enfants
- Consultation des bulletins
- Historique des consultations

## 🏗️ Architecture

### Hiérarchie des Utilisateurs

L'application suit une hiérarchie claire des rôles :

1. **Administrateur Principal** : Super administrateur qui gère l'ensemble du système
2. **Universités** : Administrateurs universitaires
3. **Professeurs**
4. **Étudiants**
5. **Parents**

### Règles de Gestion
L'application respecte les 13 règles de gestion (RG1 à RG13), incluant :
- Gestion des comptes
- Gestion des UV, UFR, filières
- Saisie des évaluations
- Vérification parentale
- Organisation académique

## 🚀 Installation

### Prérequis
- Apache/Nginx
- PHP 7.4+
- MySQL 5.7+
- WAMP/XAMPP/MAMP

### Étapes
1. Cloner le projet
2. Importer `database.sql`
3. Configurer les accès DB
4. Lancer Apache + MySQL
5. Accéder à `http://localhost/resultats_universitaires`

## 👥 Comptes de Démonstration

| Type | Identifiant | Mot de passe |
|------|-------------|--------------|
| Étudiant | etudiant | 123456 |
| Professeur | professeur | 123456 |
| Admin Principal | admin_principal | 123456 |
| Université | universite | 123456 |
| Parent | parent | 123456 |

## 🎨 Technologies

- HTML5, CSS3, Bootstrap 5
- JavaScript (ES6+)
- PHP (PDO)
- MySQL

## 📁 Structure

