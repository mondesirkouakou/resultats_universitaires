<?php
// Test direct de login.php avec debug
require_once 'config.php';
require_once 'includes/user_accounts.php';

echo "<h2>🔍 Test Debug Login.php</h2>";

if ($_POST) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $user_type = $_POST['user_type'] ?? '';
    
    echo "<h3>Données reçues:</h3>";
    echo "Email: " . htmlspecialchars($username) . "<br>";
    echo "Type: " . htmlspecialchars($user_type) . "<br>";
    echo "Password length: " . strlen($password) . "<br><br>";
    
    if (empty($username) || empty($password) || empty($user_type)) {
        echo "<p style='color: red;'>❌ Champs vides détectés</p>";
    } else {
        try {
            $pdo = getDatabaseConnection();
            echo "<p style='color: green;'>✅ Connexion DB OK</p>";
            
            if ($user_type === 'etudiant' || $user_type === 'professeur') {
                echo "<h3>Test authenticateUser():</h3>";
                $auth_result = authenticateUser($pdo, $username, $password);
                
                echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 3px;'>";
                print_r($auth_result);
                echo "</pre>";
                
                if ($auth_result['success']) {
                    echo "<p style='color: green;'>✅ Authentification réussie</p>";
                    
                    // Test de premiere_connexion
                    $premiere_connexion = !empty($auth_result['user_data']['premiere_connexion']);
                    echo "<p><strong>premiere_connexion value:</strong> " . var_export($auth_result['user_data']['premiere_connexion'], true) . "</p>";
                    echo "<p><strong>premiere_connexion boolean:</strong> " . ($premiere_connexion ? 'true' : 'false') . "</p>";
                    
                    if ($premiere_connexion) {
                        echo "<p style='color: orange;'>🔄 Devrait rediriger vers change_password.php</p>";
                    } else {
                        if ($user_type === 'etudiant') {
                            echo "<p style='color: blue;'>🔄 Devrait rediriger vers student_dashboard.php</p>";
                        } else {
                            echo "<p style='color: blue;'>🔄 Devrait rediriger vers professor_dashboard.php</p>";
                        }
                    }
                    
                    // Test des sessions
                    echo "<h3>Test de création de session:</h3>";
                    $_SESSION['user_type'] = $auth_result['user_data']['type'];
                    $_SESSION['user_id'] = $auth_result['user_id'];
                    $_SESSION['user_email'] = $auth_result['email'];
                    $_SESSION['premiere_connexion'] = $auth_result['user_data']['premiere_connexion'];
                    
                    echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 3px;'>";
                    print_r($_SESSION);
                    echo "</pre>";
                    
                } else {
                    echo "<p style='color: red;'>❌ Échec authentification: " . $auth_result['message'] . "</p>";
                }
            } elseif ($user_type === 'universite') {
                echo "<h3>Test authenticateUniversite():</h3>";
                $auth_result = authenticateUniversite($pdo, $username, $password);

                echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 3px;'>";
                print_r($auth_result);
                echo "</pre>";

                if ($auth_result['success']) {
                    echo "<p style='color: green;'>✅ Authentification université réussie</p>";

                    $premiere_connexion = !empty($auth_result['user_data']['premiere_connexion']);
                    echo "<p><strong>premiere_connexion value:</strong> " . var_export($auth_result['user_data']['premiere_connexion'], true) . "</p>";
                    echo "<p><strong>premiere_connexion boolean:</strong> " . ($premiere_connexion ? 'true' : 'false') . "</p>";

                    if ($premiere_connexion) {
                        echo "<p style='color: orange;'>🔄 Devrait rediriger vers change_password.php</p>";
                    } else {
                        echo "<p style='color: blue;'>🔄 Devrait rediriger vers admin/universite_dashboard.php</p>";
                    }

                    echo "<h3>Test de création de session:</h3>";
                    $_SESSION['user_type'] = $auth_result['user_data']['type'];
                    $_SESSION['user_id'] = $auth_result['user_id'];
                    $_SESSION['user_email'] = $auth_result['email'];
                    $_SESSION['premiere_connexion'] = $auth_result['user_data']['premiere_connexion'];

                    echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 3px;'>";
                    print_r($_SESSION);
                    echo "</pre>";
                } else {
                    echo "<p style='color: red;'>❌ Échec authentification université: " . $auth_result['message'] . "</p>";
                }
            } elseif ($user_type === 'admin') {
                echo "<h3>Test compte démo administrateur:</h3>";
                $ok = isset(DEMO_USERS['admin_principal']) 
                    && DEMO_USERS['admin_principal']['username'] === 'admin_principal'
                    && $username === 'admin_principal'
                    && DEMO_USERS['admin_principal']['password'] === $password;

                echo $ok ? "<p style='color: green;'>✅ Identifiants admin de démo valides</p>" : "<p style='color: red;'>❌ Identifiants admin de démo invalides</p>";
            } else {
                echo "<p style='color: red;'>❌ Type d'utilisateur non supporté: " . htmlspecialchars($user_type) . "</p>";
            }
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Erreur: " . $e->getMessage() . "</p>";
        }
    }
}
?>

<form method="POST" style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;">
    <h3>🔐 Simuler login.php</h3>
    <div style="margin-bottom: 15px;">
        <label><strong>Type d'utilisateur:</strong></label><br>
        <select name="user_type" style="width: 100%; max-width: 400px; padding: 8px; margin-top: 5px;" required>
            <option value="">Sélectionnez...</option>
            <option value="etudiant" <?php echo ($_POST['user_type'] ?? '') === 'etudiant' ? 'selected' : ''; ?>>Étudiant</option>
            <option value="professeur" <?php echo ($_POST['user_type'] ?? '') === 'professeur' ? 'selected' : ''; ?>>Professeur</option>
            <option value="universite" <?php echo ($_POST['user_type'] ?? '') === 'universite' ? 'selected' : ''; ?>>Université</option>
            <option value="admin" <?php echo ($_POST['user_type'] ?? '') === 'admin' ? 'selected' : ''; ?>>Administrateur (démo)</option>
        </select>
    </div>
    <div style="margin-bottom: 15px;">
        <label><strong>Email/Nom d'utilisateur:</strong></label><br>
        <input type="text" name="username" value="<?php echo $_POST['username'] ?? ''; ?>" 
               style="width: 100%; max-width: 400px; padding: 8px; margin-top: 5px;" 
               placeholder="aureliesiami124@gmail.com" required>
    </div>
    <div style="margin-bottom: 15px;">
        <label><strong>Mot de passe:</strong></label><br>
        <input type="password" name="password" 
               style="width: 100%; max-width: 400px; padding: 8px; margin-top: 5px;" 
               placeholder="Votre mot de passe" required>
    </div>
    <button type="submit" style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer;">
        🚀 Tester Login
    </button>
</form>

<div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <h4>📋 Instructions:</h4>
    <ol>
        <li>Sélectionnez le type (Étudiant, Professeur, Université).</li>
        <li>Entrez l'email exact enregistré en base (pour Université: email de la table <code>universites</code>).</li>
        <li>Entrez le mot de passe.</li>
        <li>Cliquez "Tester Login" pour voir le résultat détaillé (success, messages, sessions, redirection attendue).</li>
        <li>Liens utiles: <a href="login.php" target="_blank">login.php</a> | <a href="debug_login.php" target="_blank">debug_login.php</a></li>
    </ol>
</div>
