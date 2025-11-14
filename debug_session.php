<?php
require_once 'config.php';

echo "<h1>Débogage de la session</h1>";

echo "<h2>État de la session :</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>Test des fonctions :</h2>";
echo "isLoggedIn(): " . (isLoggedIn() ? 'true' : 'false') . "<br>";
echo "hasPermission('universite'): " . (hasPermission('universite') ? 'true' : 'false') . "<br>";
echo "hasPermission('admin'): " . (hasPermission('admin') ? 'true' : 'false') . "<br>";

echo "<h2>Test de la base de données :</h2>";
$pdo = getDatabaseConnection();
if ($pdo) {
    echo "✓ Connexion à la base de données réussie<br>";
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM filieres");
        $result = $stmt->fetch();
        echo "✓ Nombre de filières dans la base : " . $result['count'] . "<br>";
    } catch (PDOException $e) {
        echo "✗ Erreur lors de la requête : " . $e->getMessage() . "<br>";
    }
} else {
    echo "✗ Impossible de se connecter à la base de données<br>";
}

echo "<h2>Simulation de session université :</h2>";
$_SESSION['user_type'] = 'universite';
$_SESSION['username'] = 'universite';
$_SESSION['user_id'] = 1;

echo "Session mise à jour :<br>";
echo "isLoggedIn(): " . (isLoggedIn() ? 'true' : 'false') . "<br>";
echo "hasPermission('universite'): " . (hasPermission('universite') ? 'true' : 'false') . "<br>";

echo "<h2>Test de la page filières :</h2>";
ob_start();
include 'admin/filieres.php';
$output = ob_get_clean();

if (strpos($output, 'Erreur lors de la récupération des filières') !== false) {
    echo "✗ Erreur détectée dans filieres.php<br>";
    // Extraire le message d'erreur complet
    preg_match('/Erreur lors de la récupération des filières[^<]*/', $output, $matches);
    if (!empty($matches)) {
        echo "Message d'erreur: " . htmlspecialchars($matches[0]) . "<br>";
    }
} else {
    echo "✓ Page filieres.php fonctionne correctement<br>";
}
?> 