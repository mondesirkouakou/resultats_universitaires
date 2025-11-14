<?php
require_once 'config.php';
require_once 'includes/user_accounts.php';

echo "<h2>Debug - Connexion Étudiant</h2>";

// Test avec un email étudiant spécifique - MODIFIEZ CES VALEURS
$test_email = ""; // ENTREZ L'EMAIL DE L'ÉTUDIANT ICI
$test_password = ""; // ENTREZ LE MOT DE PASSE ICI

// Si pas de valeurs de test, afficher le formulaire
if (empty($test_email) || empty($test_password)) {
    echo '<form method="POST" style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
        <h3>🔍 Test de Connexion Étudiant</h3>
        <div style="margin-bottom: 15px;">
            <label>Email de l\'étudiant:</label><br>
            <input type="email" name="test_email" value="' . ($_POST['test_email'] ?? '') . '" style="width: 300px; padding: 8px;" required>
        </div>
        <div style="margin-bottom: 15px;">
            <label>Mot de passe:</label><br>
            <input type="password" name="test_password" style="width: 300px; padding: 8px;" required>
        </div>
        <button type="submit" style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 3px;">Tester la connexion</button>
    </form>';
    
    if ($_POST) {
        $test_email = $_POST['test_email'] ?? '';
        $test_password = $_POST['test_password'] ?? '';
    } else {
        echo '<p><strong>⚠️ Veuillez entrer vos identifiants d\'étudiant ci-dessus pour tester la connexion.</strong></p>';
        exit;
    }
}

echo "<h3>1. Test de connexion avec:</h3>";
echo "Email: " . htmlspecialchars($test_email) . "<br>";
echo "Mot de passe: " . htmlspecialchars($test_password) . "<br><br>";

try {
    $pdo = getDatabaseConnection();
    echo "<h3>2. Connexion à la base de données: OK</h3>";
    
    // Vérifier si l'étudiant existe
    $stmt = $pdo->prepare("SELECT id, nom, prenom, email, mot_de_passe, compte_actif, premiere_connexion FROM etudiants WHERE email = ?");
    $stmt->execute([$test_email]);
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>3. Recherche de l'étudiant:</h3>";
    if ($etudiant) {
        echo "✅ Étudiant trouvé:<br>";
        echo "- ID: " . $etudiant['id'] . "<br>";
        echo "- Nom: " . htmlspecialchars($etudiant['nom']) . "<br>";
        echo "- Prénom: " . htmlspecialchars($etudiant['prenom']) . "<br>";
        echo "- Email: " . htmlspecialchars($etudiant['email']) . "<br>";
        echo "- Compte actif: " . ($etudiant['compte_actif'] ? 'Oui' : 'Non') . "<br>";
        echo "- Première connexion: " . ($etudiant['premiere_connexion'] ? 'Oui' : 'Non') . "<br>";
        echo "- Hash du mot de passe: " . substr($etudiant['mot_de_passe'], 0, 20) . "...<br><br>";
        
        // Test de vérification du mot de passe
        echo "<h3>4. Vérification du mot de passe:</h3>";
        if (password_verify($test_password, $etudiant['mot_de_passe'])) {
            echo "✅ Mot de passe correct<br><br>";
        } else {
            echo "❌ Mot de passe incorrect<br>";
            echo "Hash stocké: " . $etudiant['mot_de_passe'] . "<br>";
            echo "Hash du mot de passe testé: " . password_hash($test_password, PASSWORD_DEFAULT) . "<br><br>";
        }
        
        // Test de la fonction authenticateUser
        echo "<h3>5. Test de la fonction authenticateUser:</h3>";
        $result = authenticateUser($pdo, $test_email, $test_password);
        echo "Résultat: <pre>" . print_r($result, true) . "</pre>";
        
    } else {
        echo "❌ Aucun étudiant trouvé avec cet email<br><br>";
        
        // Lister tous les étudiants avec comptes actifs
        echo "<h3>Étudiants avec comptes actifs:</h3>";
        $stmt = $pdo->query("SELECT id, nom, prenom, email, compte_actif FROM etudiants WHERE compte_actif = 1");
        $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($etudiants) {
            foreach ($etudiants as $e) {
                echo "- " . htmlspecialchars($e['nom']) . " " . htmlspecialchars($e['prenom']) . " (" . htmlspecialchars($e['email']) . ")<br>";
            }
        } else {
            echo "Aucun étudiant avec compte actif trouvé.<br>";
        }
    }
    
    // Vérifier les sessions
    echo "<h3>6. État des sessions:</h3>";
    echo "Session ID: " . session_id() . "<br>";
    echo "Variables de session: <pre>" . print_r($_SESSION, true) . "</pre>";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "<br>";
    echo "Trace: <pre>" . $e->getTraceAsString() . "</pre>";
}
?>
