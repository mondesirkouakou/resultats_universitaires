<?php
require_once 'config.php';
require_once 'includes/user_accounts.php';

echo "<h2>🔍 Debug de la Connexion</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { color: blue; font-weight: bold; }
    .debug-box { border: 1px solid #ddd; padding: 15px; margin: 10px 0; background: #f9f9f9; }
    pre { background: #f0f0f0; padding: 10px; border-radius: 5px; }
</style>";

try {
    $pdo = getDatabaseConnection();
    echo "<div class='success'>✅ Connexion à la base de données réussie</div>";
    
    // 1. Vérifier les étudiants avec comptes
    echo "<div class='debug-box'>";
    echo "<h3>1. Étudiants avec comptes actifs</h3>";
    
    $stmt = $pdo->query("
        SELECT id, nom, prenom, email, 
               CASE WHEN mot_de_passe IS NOT NULL THEN 'OUI' ELSE 'NON' END as a_compte,
               compte_actif, premiere_connexion, date_creation_compte
        FROM etudiants 
        WHERE email IS NOT NULL AND email != ''
        ORDER BY date_creation_compte DESC
        LIMIT 5
    ");
    $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($etudiants)) {
        echo "<div class='error'>❌ Aucun étudiant trouvé avec email</div>";
        echo "<p><strong>Action :</strong> Créez d'abord un étudiant avec email via admin/etudiants.php</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Nom</th><th>Email</th><th>A un compte</th><th>Actif</th><th>1ère connexion</th></tr>";
        foreach ($etudiants as $etudiant) {
            echo "<tr>";
            echo "<td>" . $etudiant['id'] . "</td>";
            echo "<td>" . htmlspecialchars($etudiant['nom'] . ' ' . $etudiant['prenom']) . "</td>";
            echo "<td>" . htmlspecialchars($etudiant['email']) . "</td>";
            echo "<td>" . $etudiant['a_compte'] . "</td>";
            echo "<td>" . ($etudiant['compte_actif'] ? 'OUI' : 'NON') . "</td>";
            echo "<td>" . ($etudiant['premiere_connexion'] ? 'OUI' : 'NON') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // 2. Vérifier les professeurs avec comptes
    echo "<div class='debug-box'>";
    echo "<h3>2. Professeurs avec comptes actifs</h3>";
    
    $stmt = $pdo->query("
        SELECT id, nom, prenom, email, 
               CASE WHEN mot_de_passe IS NOT NULL THEN 'OUI' ELSE 'NON' END as a_compte,
               compte_actif, premiere_connexion, date_creation_compte
        FROM professeurs 
        WHERE email IS NOT NULL AND email != ''
        ORDER BY date_creation_compte DESC
        LIMIT 5
    ");
    $professeurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($professeurs)) {
        echo "<div class='error'>❌ Aucun professeur trouvé avec email</div>";
        echo "<p><strong>Action :</strong> Créez d'abord un professeur avec email via admin/professeurs.php</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Nom</th><th>Email</th><th>A un compte</th><th>Actif</th><th>1ère connexion</th></tr>";
        foreach ($professeurs as $professeur) {
            echo "<tr>";
            echo "<td>" . $professeur['id'] . "</td>";
            echo "<td>" . htmlspecialchars($professeur['nom'] . ' ' . $professeur['prenom']) . "</td>";
            echo "<td>" . htmlspecialchars($professeur['email']) . "</td>";
            echo "<td>" . $professeur['a_compte'] . "</td>";
            echo "<td>" . ($professeur['compte_actif'] ? 'OUI' : 'NON') . "</td>";
            echo "<td>" . ($professeur['premiere_connexion'] ? 'OUI' : 'NON') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // 3. Test d'authentification
    echo "<div class='debug-box'>";
    echo "<h3>3. Test d'authentification</h3>";
    
    if (!empty($etudiants)) {
        $test_etudiant = $etudiants[0];
        if ($test_etudiant['a_compte'] === 'OUI') {
            echo "<p><strong>Test avec l'étudiant :</strong> " . htmlspecialchars($test_etudiant['email']) . "</p>";
            
            // Récupérer le mot de passe hashé pour info
            $stmt = $pdo->prepare("SELECT mot_de_passe FROM etudiants WHERE id = ?");
            $stmt->execute([$test_etudiant['id']]);
            $hash_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "<p><strong>Hash du mot de passe :</strong> " . substr($hash_info['mot_de_passe'], 0, 20) . "...</p>";
            
            // Test avec un mot de passe fictif
            echo "<p><strong>Test d'authentification avec mot de passe 'test123' :</strong></p>";
            $auth_result = authenticateUser($pdo, $test_etudiant['email'], 'test123', 'etudiant');
            echo "<pre>";
            print_r($auth_result);
            echo "</pre>";
        }
    }
    echo "</div>";
    
    // 4. Vérifier les fichiers requis
    echo "<div class='debug-box'>";
    echo "<h3>4. Vérification des fichiers</h3>";
    
    $required_files = [
        'change_password.php',
        'student_login.php',
        'professor_dashboard.php',
        'includes/user_accounts.php'
    ];
    
    foreach ($required_files as $file) {
        if (file_exists($file)) {
            echo "<div class='success'>✅ $file existe</div>";
        } else {
            echo "<div class='error'>❌ $file manquant</div>";
        }
    }
    echo "</div>";
    
    // 5. Instructions de test
    echo "<div class='debug-box'>";
    echo "<h3>5. Instructions de test</h3>";
    echo "<ol>";
    echo "<li><strong>Créer un compte test :</strong>";
    echo "<ul>";
    echo "<li>Allez sur <a href='admin/etudiants.php' target='_blank'>admin/etudiants.php</a></li>";
    echo "<li>Créez un étudiant avec un email valide</li>";
    echo "<li>Cliquez sur le bouton '👤+' pour créer le compte</li>";
    echo "<li>Notez bien l'email et le mot de passe temporaire affiché</li>";
    echo "</ul></li>";
    echo "<li><strong>Tester la connexion :</strong>";
    echo "<ul>";
    echo "<li>Allez sur <a href='login.php' target='_blank'>login.php</a></li>";
    echo "<li>Sélectionnez 'Étudiant'</li>";
    echo "<li>Saisissez l'email EXACT et le mot de passe temporaire</li>";
    echo "<li>Cliquez sur 'Se connecter'</li>";
    echo "</ul></li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='login.php'>🔗 Aller à la page de connexion</a>";
echo " | <a href='admin/etudiants.php'>🔗 Gestion des étudiants</a>";
echo " | <a href='admin/professeurs.php'>🔗 Gestion des professeurs</a>";
?>
