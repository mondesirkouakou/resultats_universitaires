<?php
/**
 * Fichier de test pour vérifier l'installation du portail des résultats universitaires
 * 
 * Ce fichier permet de diagnostiquer les problèmes d'installation
 * Supprimez ce fichier après avoir vérifié que tout fonctionne
 */

// Désactiver l'affichage des erreurs pour ce test
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Test d'installation - Portail des Résultats Universitaires</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        .test-section { margin-bottom: 30px; }
        .test-success { color: #198754; }
        .test-error { color: #dc3545; }
        .test-warning { color: #ffc107; }
    </style>
</head>
<body>
    <div class='container mt-5'>
        <h1 class='text-center mb-5'>Test d'installation du portail</h1>";

// Test 1: Version PHP
echo "<div class='test-section'>
    <h3>1. Version PHP</h3>";
if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    echo "<p class='test-success'>✅ PHP " . PHP_VERSION . " - Version compatible</p>";
} else {
    echo "<p class='test-error'>❌ PHP " . PHP_VERSION . " - Version trop ancienne (minimum 7.4)</p>";
}
echo "</div>";

// Test 2: Extensions PHP requises
echo "<div class='test-section'>
    <h3>2. Extensions PHP</h3>";
$required_extensions = ['pdo', 'pdo_mysql', 'session', 'json', 'mbstring'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<p class='test-success'>✅ Extension $ext - Installée</p>";
    } else {
        echo "<p class='test-error'>❌ Extension $ext - Manquante</p>";
    }
}
echo "</div>";

// Test 3: Permissions des dossiers
echo "<div class='test-section'>
    <h3>3. Permissions des dossiers</h3>";
$directories = ['assets', 'assets/css', 'assets/js'];
foreach ($directories as $dir) {
    if (is_dir($dir) && is_readable($dir)) {
        echo "<p class='test-success'>✅ Dossier $dir - Accessible</p>";
    } else {
        echo "<p class='test-error'>❌ Dossier $dir - Inaccessible</p>";
    }
}
echo "</div>";

// Test 4: Fichiers requis
echo "<div class='test-section'>
    <h3>4. Fichiers requis</h3>";
$required_files = ['config.php', 'index.php', 'login.php', 'dashboard.php', 'database.sql'];
foreach ($required_files as $file) {
    if (file_exists($file) && is_readable($file)) {
        echo "<p class='test-success'>✅ Fichier $file - Présent</p>";
    } else {
        echo "<p class='test-error'>❌ Fichier $file - Manquant</p>";
    }
}
echo "</div>";

// Test 5: Connexion à la base de données
echo "<div class='test-section'>
    <h3>5. Connexion à la base de données</h3>";
try {
    require_once 'config.php';
    $pdo = getDatabaseConnection();
    if ($pdo) {
        echo "<p class='test-success'>✅ Connexion à la base de données - Réussie</p>";
        
        // Test des tables
        $tables = ['comptes', 'etudiants', 'professeurs', 'resultats', 'evaluations'];
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) {
                    echo "<p class='test-success'>✅ Table $table - Présente</p>";
                } else {
                    echo "<p class='test-warning'>⚠️ Table $table - Manquante (importez database.sql)</p>";
                }
            } catch (Exception $e) {
                echo "<p class='test-error'>❌ Erreur lors de la vérification de la table $table</p>";
            }
        }
    } else {
        echo "<p class='test-error'>❌ Connexion à la base de données - Échec</p>";
    }
} catch (Exception $e) {
    echo "<p class='test-error'>❌ Erreur de configuration: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 6: Configuration
echo "<div class='test-section'>
    <h3>6. Configuration</h3>";
if (defined('APP_NAME')) {
    echo "<p class='test-success'>✅ Configuration chargée - " . APP_NAME . "</p>";
} else {
    echo "<p class='test-error'>❌ Configuration non chargée</p>";
}
echo "</div>";

// Test 7: Sessions
echo "<div class='test-section'>
    <h3>7. Sessions PHP</h3>";
if (session_status() === PHP_SESSION_ACTIVE || session_start()) {
    echo "<p class='test-success'>✅ Sessions PHP - Fonctionnelles</p>";
} else {
    echo "<p class='test-error'>❌ Sessions PHP - Problème</p>";
}
echo "</div>";

// Test 8: Fonctions utilitaires
echo "<div class='test-section'>
    <h3>8. Fonctions utilitaires</h3>";
if (function_exists('validateInput') && function_exists('sanitizeInput')) {
    echo "<p class='test-success'>✅ Fonctions utilitaires - Disponibles</p>";
} else {
    echo "<p class='test-error'>❌ Fonctions utilitaires - Manquantes</p>";
}
echo "</div>";

// Test 9: Serveur web
echo "<div class='test-section'>
    <h3>9. Serveur web</h3>";
$server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Inconnu';
echo "<p class='test-success'>✅ Serveur web - $server_software</p>";
echo "</div>";

// Test 10: URL de base
echo "<div class='test-section'>
    <h3>10. URL de base</h3>";
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$base_url = dirname($base_url);
echo "<p class='test-success'>✅ URL de base - $base_url</p>";
echo "</div>";

// Recommandations
echo "<div class='test-section'>
    <h3>Recommandations</h3>
    <div class='alert alert-info'>
        <h5>Pour une installation complète :</h5>
        <ol>
            <li>Importez le fichier <code>database.sql</code> dans votre base de données</li>
            <li>Vérifiez que tous les tests ci-dessus sont verts</li>
            <li>Testez la connexion avec les comptes de démonstration</li>
            <li>Supprimez ce fichier <code>test.php</code> après vérification</li>
        </ol>
    </div>
</div>";

// Liens de test
echo "<div class='test-section'>
    <h3>Liens de test</h3>
    <div class='row'>
        <div class='col-md-3'>
            <a href='index.php' class='btn btn-primary w-100 mb-2'>Page d'accueil</a>
        </div>
        <div class='col-md-3'>
            <a href='login.php' class='btn btn-success w-100 mb-2'>Page de connexion</a>
        </div>
        <div class='col-md-3'>
            <a href='dashboard.php' class='btn btn-warning w-100 mb-2'>Tableau de bord</a>
        </div>
        <div class='col-md-3'>
            <a href='database.sql' class='btn btn-info w-100 mb-2'>Script SQL</a>
        </div>
    </div>
</div>";

// Informations système
echo "<div class='test-section'>
    <h3>Informations système</h3>
    <table class='table table-striped'>
        <tr><td><strong>PHP Version</strong></td><td>" . PHP_VERSION . "</td></tr>
        <tr><td><strong>Serveur</strong></td><td>" . ($_SERVER['SERVER_SOFTWARE'] ?? 'Inconnu') . "</td></tr>
        <tr><td><strong>Document Root</strong></td><td>" . ($_SERVER['DOCUMENT_ROOT'] ?? 'Inconnu') . "</td></tr>
        <tr><td><strong>Script Path</strong></td><td>" . __FILE__ . "</td></tr>
        <tr><td><strong>Memory Limit</strong></td><td>" . ini_get('memory_limit') . "</td></tr>
        <tr><td><strong>Max Execution Time</strong></td><td>" . ini_get('max_execution_time') . "s</td></tr>
        <tr><td><strong>Upload Max Filesize</strong></td><td>" . ini_get('upload_max_filesize') . "</td></tr>
    </table>
</div>";

echo "</div>
</body>
</html>";
?> 