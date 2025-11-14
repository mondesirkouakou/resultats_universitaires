<?php
// Test simple de connexion étudiant
require_once 'config.php';
require_once 'includes/user_accounts.php';

echo "<h2>🔍 Test Simple de Connexion</h2>";

if ($_POST) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    echo "<h3>Test avec: " . htmlspecialchars($email) . "</h3>";
    
    try {
        $pdo = getDatabaseConnection();
        
        // Test direct de la fonction authenticateUser
        echo "<p><strong>Étape 1:</strong> Test de authenticateUser()...</p>";
        $result = authenticateUser($pdo, $email, $password);
        
        echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 3px;'>";
        print_r($result);
        echo "</pre>";
        
        if ($result['success']) {
            echo "<p style='color: green;'>✅ <strong>Authentification réussie!</strong></p>";
            
            // Test de création de session
            echo "<p><strong>Étape 2:</strong> Test de création de session...</p>";
            $_SESSION['user_id'] = $result['user_data']['id'];
            $_SESSION['user_type'] = $result['user_data']['type'];
            $_SESSION['user_email'] = $result['user_data']['email'];
            $_SESSION['premiere_connexion'] = $result['user_data']['premiere_connexion'];
            
            echo "<p>Session créée:</p>";
            echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 3px;'>";
            print_r($_SESSION);
            echo "</pre>";
            
            // Test de redirection
            echo "<p><strong>Étape 3:</strong> Test de redirection...</p>";
            if ($result['user_data']['premiere_connexion']) {
                echo "<p style='color: orange;'>🔄 Devrait rediriger vers change_password.php</p>";
                echo "<p><a href='change_password.php' target='_blank'>➡️ Tester change_password.php</a></p>";
            } else {
                $dashboard = $result['user_data']['type'] === 'etudiant' ? 'student_dashboard.php' : 'professor_dashboard.php';
                echo "<p style='color: blue;'>🔄 Devrait rediriger vers $dashboard</p>";
                echo "<p><a href='$dashboard' target='_blank'>➡️ Tester $dashboard</a></p>";
            }
            
        } else {
            echo "<p style='color: red;'>❌ <strong>Échec de l'authentification:</strong> " . $result['message'] . "</p>";
            
            // Vérifier si l'étudiant existe
            echo "<p><strong>Vérification:</strong> L'étudiant existe-t-il?</p>";
            $stmt = $pdo->prepare("SELECT id, nom, prenom, email, compte_actif, premiere_connexion FROM etudiants WHERE email = ?");
            $stmt->execute([$email]);
            $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($etudiant) {
                echo "<p style='color: green;'>✅ Étudiant trouvé dans la base</p>";
                echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 3px;'>";
                print_r($etudiant);
                echo "</pre>";
            } else {
                echo "<p style='color: red;'>❌ Aucun étudiant trouvé avec cet email</p>";
                
                // Lister les étudiants disponibles
                $stmt = $pdo->query("SELECT email, compte_actif FROM etudiants LIMIT 5");
                $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo "<p><strong>Étudiants disponibles (5 premiers):</strong></p>";
                echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 3px;'>";
                print_r($etudiants);
                echo "</pre>";
            }
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ <strong>Erreur:</strong> " . $e->getMessage() . "</p>";
    }
}
?>

<form method="POST" style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;">
    <h3>🔐 Tester la Connexion</h3>
    <div style="margin-bottom: 15px;">
        <label><strong>Email de l'étudiant:</strong></label><br>
        <input type="email" name="email" value="<?php echo $_POST['email'] ?? ''; ?>" 
               style="width: 100%; max-width: 400px; padding: 8px; margin-top: 5px;" 
               placeholder="exemple@universite.fr" required>
    </div>
    <div style="margin-bottom: 15px;">
        <label><strong>Mot de passe:</strong></label><br>
        <input type="password" name="password" 
               style="width: 100%; max-width: 400px; padding: 8px; margin-top: 5px;" 
               placeholder="Votre mot de passe" required>
    </div>
    <button type="submit" style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer;">
        🚀 Tester la Connexion
    </button>
</form>

<div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <h4>📋 Instructions:</h4>
    <ol>
        <li>Entrez l'email et le mot de passe de l'étudiant qui ne peut pas se connecter</li>
        <li>Cliquez sur "Tester la Connexion"</li>
        <li>Analysez les résultats pour identifier le problème</li>
    </ol>
</div>
