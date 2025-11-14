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
   - Création et gestion des universités
   - Supervision globale
   - Statistiques système

2. **Universités** : Administrateurs universitaires qui gèrent leurs propres données
   - Gestion des filières, matières, classes
   - Gestion des étudiants et professeurs
   - Affectations et statistiques universitaires

3. **Professeurs** : Saisie des notes et gestion des évaluations
4. **Étudiants** : Consultation des résultats
5. **Parents** : Suivi des résultats de leurs enfants

### Règles de Gestion

L'application respecte les 13 règles de gestion (RG1 à RG13) :

- **RG1** : Gestion des comptes étudiants
- **RG2** : Consultation des résultats
- **RG3** : Gestion des matières et évaluations
- **RG4** : Administration des comptes
- **RG5** : Organisation des filières et UV
- **RG6** : Visualisation des matières par compte
- **RG7** : Inscription aux UV
- **RG8** : Structure université/UFR
- **RG9** : Périodes académiques
- **RG10** : Saisie des évaluations par professeurs
- **RG11** : Vérification des comptes par les parents
- **RG12** : Attribution des professeurs aux UFR
- **RG13** : Organisation université/filières

## 🚀 Installation

### Prérequis
- Serveur web (Apache/Nginx)
- PHP 7.4 ou supérieur
- MySQL 5.7 ou supérieur
- WAMP/XAMPP/MAMP (recommandé)

### Étapes d'installation

1. **Cloner ou télécharger le projet**
   ```bash
   git clone [url-du-repo]
   cd resultats_universitaires
   ```

2. **Configurer la base de données**
   - Ouvrir phpMyAdmin ou votre client MySQL
   - Créer une nouvelle base de données
   - Importer le fichier `database.sql`

3. **Configurer la connexion à la base de données**
   - Modifier les paramètres de connexion dans `login.php` et `dashboard.php`
   ```php
   $config = [
       'host' => 'localhost',
       'dbname' => 'resultats_universitaires',
       'username' => 'root',
       'password' => ''
   ];
   ```

4. **Démarrer le serveur web**
   - Placer le projet dans le dossier `www` de WAMP/XAMPP
   - Démarrer Apache et MySQL

5. **Accéder à l'application**
   - Ouvrir votre navigateur
   - Aller à `http://localhost/resultats_universitaires`

## 👥 Utilisation

### Comptes de démonstration

L'application inclut des comptes de démonstration pour tester les différentes fonctionnalités :

| Type d'utilisateur | Nom d'utilisateur | Mot de passe |
|-------------------|-------------------|--------------|
| Étudiant | `etudiant` | `123456` |
| Professeur | `professeur` | `123456` |
| Admin Principal | `admin_principal` | `123456` |
| Université | `universite` | `123456` |
| Parent | `parent` | `123456` |

### Navigation

1. **Page d'accueil** : Présentation de l'application
2. **Connexion** : Sélection du type d'utilisateur et authentification
3. **Tableau de bord** : Interface adaptée selon le type d'utilisateur

## 🎨 Technologies utilisées

### Frontend
- **HTML5** : Structure sémantique
- **CSS3** : Styles modernes avec variables CSS
- **Bootstrap 5** : Framework responsive
- **Font Awesome** : Icônes
- **JavaScript** : Interactions et animations

### Backend
- **PHP** : Logique métier
- **MySQL** : Base de données
- **PDO** : Connexion sécurisée à la base de données

### Fonctionnalités avancées
- **Animations CSS** : Transitions fluides
- **JavaScript moderne** : ES6+ features
- **Responsive Design** : Adaptation mobile
- **Validation de formulaires** : Côté client et serveur
- **Système de sessions** : Sécurité

## 📁 Structure du projet

```
resultats_universitaires/
├── index.php              # Page d'accueil
├── login.php              # Page de connexion
├── dashboard.php          # Tableau de bord principal
├── logout.php             # Déconnexion
├── database.sql           # Script de création de la base de données
├── README.md              # Documentation
├── assets/
│   ├── css/
│   │   └── style.css      # Styles personnalisés
│   └── js/
│       └── main.js        # JavaScript principal
└── includes/              # Fichiers d'inclusion (futur)
```

## 🔧 Configuration

### Personnalisation des couleurs
Modifier les variables CSS dans `assets/css/style.css` :
```css
:root {
    --primary-color: #0d6efd;
    --secondary-color: #6c757d;
    --success-color: #198754;
    /* ... autres couleurs */
}
```

### Ajout de nouvelles fonctionnalités
1. Créer les tables nécessaires dans la base de données
2. Ajouter les pages PHP correspondantes
3. Mettre à jour la navigation dans `dashboard.php`
4. Ajouter les styles CSS si nécessaire

## 🛡️ Sécurité

### Mesures implémentées
- **Validation des entrées** : Côté client et serveur
- **Protection contre les injections SQL** : Utilisation de PDO
- **Gestion des sessions** : Authentification sécurisée
- **Échappement des sorties** : Protection XSS

### Recommandations pour la production
- Utiliser HTTPS
- Configurer des mots de passe forts
- Implémenter une authentification à deux facteurs
- Mettre en place des logs de sécurité
- Effectuer des sauvegardes régulières

## 📊 Base de données

### Tables principales
- `universites` : Gestion des universités
- `ufr` : Unités de Formation et de Recherche
- `filieres` : Filières d'études
- `uv` : Unités de Valeur
- `comptes` : Comptes utilisateurs
- `etudiants` : Informations des étudiants
- `professeurs` : Informations des professeurs
- `evaluations` : Évaluations par matière
- `resultats` : Notes des étudiants
- `periodes` : Périodes académiques

### Relations clés
- Un étudiant peut avoir un seul compte (RG1)
- Un compte peut être détenu par plusieurs étudiants
- Une matière peut avoir plusieurs évaluations (RG3)
- Un professeur peut saisir plusieurs évaluations (RG10)
- Les parents peuvent vérifier les comptes de leurs enfants (RG11)

## 🚀 Déploiement

### Environnement de développement
1. Installer WAMP/XAMPP
2. Placer le projet dans le dossier `www`
3. Importer la base de données
4. Configurer les paramètres de connexion

### Environnement de production
1. Serveur web avec PHP 7.4+
2. Base de données MySQL
3. Configuration SSL
4. Sauvegarde automatique
5. Monitoring des performances

## 🤝 Contribution

### Pour contribuer au projet
1. Fork le repository
2. Créer une branche pour votre fonctionnalité
3. Implémenter les modifications
4. Tester exhaustivement
5. Soumettre une pull request

### Standards de code
- Respecter les conventions PSR
- Commenter le code complexe
- Utiliser des noms de variables explicites
- Tester les nouvelles fonctionnalités

## 📝 Licence

Ce projet est développé pour des fins éducatives et peut être utilisé librement.

## 📞 Support

Pour toute question ou problème :
- Vérifier la documentation
- Consulter les logs d'erreur
- Tester avec les comptes de démonstration
- Vérifier la configuration de la base de données

---

**Développé avec ❤️ pour la gestion académique moderne** 