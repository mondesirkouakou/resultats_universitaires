<?php
require_once 'config.php';
require_once 'includes/user_accounts.php';

echo "<h2>🔍 Debug du Processus de Connexion</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { color: blue; font-weight: bold; }
    .debug-box { border: 1px solid #ddd; padding: 15px; margin: 10px 0; background: #f9f9f9; }
    pre { background: #f0f0f0; padding: 10px; border-radius: 5px; overflow-x: auto; }
    .form-box { background: #e8f4fd; padding: 20px; border-radius: 10px; margin: 20px 0; }
</style>";

$pdo = getDatabaseConnection();

// Afficher les sessions actuelles
echo "<div class='debug-box'>";
echo "<h3>Sessions actuelles</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
echo "</div>";

// Formulaire de test de connexion
echo "<div class='form-box'>";
echo "<h3>🧪 Test de Connexion Direct</h3>";
echo "<form method='POST'>";
echo "<p><label>Email étudiant : <input type='email' name='test_email' value='koffi@gmail.com' style='width: 250px;'></label></p>";
echo "<p><label>Mot de passe : <input type='text' name='test_password' placeholder='Saisissez le mot de passe temporaire' style='width: 250px;'></label></p>";
echo "<p><label>Type : <select name='test_type'><option value='etudiant'>Étudiant</option><option value='professeur'>Professeur</option></select></label></p>";
echo "<p><button type='submit' name='test_auth'>🔍 Tester l'authentification</button></p>";
echo "</form>";
echo "</div>";

if (isset($_POST['test_auth'])) {
    $test_email = $_POST['test_email'] ?? '';
    $test_password = $_POST['test_password'] ?? '';
    $test_type = $_POST['test_type'] ?? 'etudiant';
    
    echo "<div class='debug-box'>";
    echo "<h3>🔍 Résultat du test d'authentification</h3>";
    echo "<p><strong>Email testé :</strong> " . htmlspecialchars($test_email) . "</p>";
    echo "<p><strong>Type :</strong> " . htmlspecialchars($test_type) . "</p>";
    echo "<p><strong>Mot de passe :</strong> " . str_repeat('*', strlen($test_password)) . " (longueur: " . strlen($test_password) . ")</p>";
    
    if (empty($test_email) || empty($test_password)) {
        echo "<div class='error'>❌ Email ou mot de passe vide</div>";
    } else {
        echo "<h4>Étape 1 : Test d'authentification</h4>";
        $auth_result = authenticateUser($pdo, $test_email, $test_password, $test_type);
        echo "<pre>";
        print_r($auth_result);
        echo "</pre>";
        
        if ($auth_result['success']) {
            echo "<div class='success'>✅ Authentification réussie !</div>";
            
            echo "<h4>Étape 2 : Simulation de la session</h4>";
            $_SESSION['user_type'] = $test_type;
            $_SESSION['user_id'] = $auth_result['user_id'];
            $_SESSION['username'] = $auth_result['email'];
            $_SESSION['user_data'] = $auth_result['user_data'];
            
            echo "<p><strong>Sessions créées :</strong></p>";
            echo "<pre>";
            print_r($_SESSION);
            echo "</pre>";
            
            echo "<h4>Étape 3 : Test de redirection</h4>";
            if ($auth_result['user_data']['premiere_connexion']) {
                echo "<div class='info'>🔄 Devrait rediriger vers change_password.php (première connexion)</div>";
                echo "<p><a href='change_password.php' target='_blank'>🔗 Tester change_password.php</a></p>";
            } else {
                if ($test_type === 'etudiant') {
                    echo "<div class='info'>🔄 Devrait rediriger vers dashboard.php</div>";
                    echo "<p><a href='dashboard.php' target='_blank'>🔗 Tester dashboard.php</a></p>";
                } else {
                    echo "<div class='info'>🔄 Devrait rediriger vers professor_dashboard.php</div>";
                    echo "<p><a href='professor_dashboard.php' target='_blank'>🔗 Tester professor_dashboard.php</a></p>";
                }
            }
            
            echo "<h4>Étape 4 : Test des fichiers de destination</h4>";
            $files_to_check = ['change_password.php', 'dashboard.php', 'professor_dashboard.php'];
            foreach ($files_to_check as $file) {
                if (file_exists($file)) {
                    echo "<div class='success'>✅ $file existe</div>";
                    
                    // Vérifier si le fichier est accessible
                    $file_content = file_get_contents($file, false, null, 0, 200);
                    if (strpos($file_content, '<?php') !== false) {
                        echo "<div class='success'>✅ $file semble être un fichier PHP valide</div>";
                    } else {
                        echo "<div class='error'>❌ $file ne semble pas être un fichier PHP valide</div>";
                    }
                } else {
                    echo "<div class='error'>❌ $file n'existe pas</div>";
                }
            }
            
        } else {
            echo "<div class='error'>❌ Authentification échouée : " . htmlspecialchars($auth_result['message']) . "</div>";
        }
    }
    echo "</div>";
}

// Vérifier les erreurs PHP
echo "<div class='debug-box'>";
echo "<h3>Configuration PHP</h3>";
echo "<p><strong>Affichage des erreurs :</strong> " . (ini_get('display_errors') ? 'Activé' : 'Désactivé') . "</p>";
echo "<p><strong>Log des erreurs :</strong> " . (ini_get('log_errors') ? 'Activé' : 'Désactivé') . "</p>";
echo "<p><strong>Niveau d'erreur :</strong> " . error_reporting() . "</p>";
echo "</div>";

// Instructions
echo "<div class='debug-box'>";
echo "<h3>📋 Instructions de test</h3>";
echo "<ol>";
echo "<li><strong>Récupérez un mot de passe temporaire :</strong>";
echo "<ul><li>Allez sur <a href='admin/etudiants.php' target='_blank'>admin/etudiants.php</a></li>";
echo "<li>Cliquez sur '👤+' à côté d'un étudiant</li>";
echo "<li>Copiez le mot de passe temporaire affiché</li></ul></li>";
echo "<li><strong>Testez ici :</strong> Collez l'email et le mot de passe dans le formulaire ci-dessus</li>";
echo "<li><strong>Vérifiez les résultats :</strong> Le script vous dira exactement où est le problème</li>";
echo "</ol>";
echo "</div>";

echo "<br><a href='login.php'>🔗 Retour à login.php</a>";
?>
