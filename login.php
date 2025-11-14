<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Initialize variables
$error = '';
$success = '';
$debug_info = [];

// Log POST data for debugging
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $debug_info[] = "POST data: " . print_r($_POST, true);
    error_log("LOGIN DEBUG - POST reçu: " . print_r($_POST, true));
}

// Include required files
require_once 'config.php';
require_once 'includes/user_accounts.php';

// Establish database connection
try {
    $pdo = getDatabaseConnection();
    if ($pdo === null) {
        throw new Exception("Failed to initialize database connection");
    }
} catch (Exception $e) {
    $error = 'Erreur de connexion à la base de données. Veuillez vérifier la configuration.';
    $debug_info[] = "Database connection error: " . $e->getMessage();
    error_log("Database connection failed: " . $e->getMessage());
    // Don't proceed with authentication if database connection failed
    $pdo = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Don't proceed with authentication if database connection failed
    if ($pdo === null) {
        $error = 'Erreur de connexion à la base de données. Veuillez contacter l\'administrateur.';
        $debug_info[] = "Authentication aborted: No database connection";
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $user_type = $_POST['user_type'] ?? '';
        
        // DEBUG: Show received data
        $debug_info[] = "Données reçues: Email='$username', Type='$user_type', Password length=" . strlen($password);
        
        if (empty($username) || empty($password) || empty($user_type)) {
            $error = 'Veuillez remplir tous les champs';
            $debug_info[] = "Erreur: Champs vides";
        } else {
            $auth_success = false;
        
        // Authentification selon le type d'utilisateur
        switch ($user_type) {
            case 'etudiant':
            case 'professeur':
                // Authentification étudiants/professeurs via la base de données
                $debug_info[] = "Tentative d'authentification pour $user_type: $username";
                $auth_result = authenticateUser($pdo, $username, $password);
                $debug_info[] = "Résultat auth: " . ($auth_result['success'] ? 'SUCCÈS' : 'ECHEC - ' . $auth_result['message']);
                
                if ($auth_result['success']) {
                    $_SESSION['user_type'] = $auth_result['user_data']['type'];
                    $_SESSION['user_id'] = $auth_result['user_id'];
                    $_SESSION['user_email'] = $auth_result['email'];
                    $_SESSION['user_nom'] = $auth_result['user_data']['nom'];
                    $_SESSION['user_prenom'] = $auth_result['user_data']['prenom'];
                    $_SESSION['premiere_connexion'] = $auth_result['user_data']['premiere_connexion'];
                    $_SESSION['user_data'] = $auth_result['user_data'];
                    // Assurer la compatibilité avec isLoggedIn() qui attend 'username'
                    $_SESSION['username'] = !empty($auth_result['user_data']['nom'])
                        ? $auth_result['user_data']['nom']
                        : $auth_result['email'];
                    
                    // Vérifier premiere_connexion - si vide, considérer comme false
                    $premiere_connexion = !empty($auth_result['user_data']['premiere_connexion']);
                    
                    // Si première connexion, rediriger vers changement de mot de passe
                    if ($premiere_connexion) {
                        header("Location: change_password.php");
                        exit();
                    } else {
                        // Rediriger vers le dashboard approprié
                        if ($user_type === 'etudiant') {
                            header("Location: student_dashboard.php");
                            exit();
                        } else {
                            header("Location: professor_dashboard.php");
                            exit();
                        }
                    }
                } else {
                    $error = $auth_result['message'];
                    $debug_info[] = "Erreur d'authentification: " . $auth_result['message'];
                }
                break;
                
            case 'admin':
                // Authentification via le système de démo pour les autres types
                $user_type_mapping = [
                    'admin' => 'admin_principal'
                ];
                
                $actual_user_type = $user_type_mapping[$user_type] ?? $user_type;
                
                if (isset(DEMO_USERS[$actual_user_type]) && 
                    DEMO_USERS[$actual_user_type]['username'] === $username && 
                    DEMO_USERS[$actual_user_type]['password'] === $password) {
                    
                    $_SESSION['user_type'] = $user_type;
                    $_SESSION['username'] = $username;
                    $_SESSION['user_id'] = rand(1000, 9999);
                    
                    // Redirection selon le type d'utilisateur
                    switch ($user_type) {
                        case 'admin':
                            header("Location: admin/dashboard.php");
                            break;
                        default:
                            header("Location: dashboard.php");
                            break;
                    }
                    exit;
                } else {
                    $error = 'Identifiants incorrects';
                }
                break;
            
            case 'universite':
                // Authentification des universités via la base de données
                $debug_info[] = "Tentative d'authentification pour universite: $username";
                $auth_result = authenticateUniversite($pdo, $username, $password);
                $debug_info[] = "Résultat auth: " . ($auth_result['success'] ? 'SUCCÈS' : 'ECHEC - ' . $auth_result['message']);
                if ($auth_result['success']) {
                    $_SESSION['user_type'] = $auth_result['user_data']['type'];
                    $_SESSION['user_id'] = $auth_result['user_id'];
                    $_SESSION['user_email'] = $auth_result['email'];
                    $_SESSION['user_nom'] = $auth_result['user_data']['nom'];
                    $_SESSION['user_prenom'] = '';
                    $_SESSION['premiere_connexion'] = $auth_result['user_data']['premiere_connexion'];
                    $_SESSION['user_data'] = $auth_result['user_data'];
                    // Assurer la compatibilité avec isLoggedIn() qui attend 'username'
                    $_SESSION['username'] = !empty($auth_result['user_data']['nom'])
                        ? $auth_result['user_data']['nom']
                        : $auth_result['email'];

                    $premiere_connexion = !empty($auth_result['user_data']['premiere_connexion']);
                    if ($premiere_connexion) {
                        header("Location: change_password.php");
                        exit();
                    }
                    header("Location: admin/universite_dashboard.php");
                    exit();
                } else {
                    $error = $auth_result['message'];
                }
                break;
                
            default:
                $error = 'Type d\'utilisateur non reconnu';
                break;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Portail des Résultats Universitaires</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .login-container {
            min-height: 100vh;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        
        .login-header {
            background: var(--gradient-primary);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .user-type-selector {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .user-type-btn {
            flex: 1;
            min-width: 80px;
            padding: 8px 4px;
            border: 2px solid #e9ecef;
            background: white;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            font-size: 0.85rem;
        }
        
        .user-type-btn.active {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
        }
        
        .user-type-btn:hover {
            border-color: var(--primary-color);
        }
        
        .form-floating {
            margin-bottom: 20px;
        }
        
        .form-floating .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 15px;
            height: auto;
        }
        
        .form-floating .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        
        .form-floating label {
            padding: 15px;
        }
        
        .btn-login {
            width: 100%;
            padding: 15px;
            border-radius: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: var(--gradient-primary);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(13, 110, 253, 0.3);
        }
        
        .back-link {
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            color: rgba(255, 255, 255, 0.8);
            transform: translateX(-5px);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <?php if ($pdo === null): ?>
                        <div class="alert alert-danger">
                            <h4>Erreur de connexion à la base de données</h4>
                            <p>Impossible de se connecter à la base de données. Veuillez vérifier :</p>
                            <ul>
                                <li>Que le serveur MySQL est en cours d'exécution</li>
                                <li>Que les identifiants de la base de données dans config.php sont corrects</li>
                                <li>Que la base de données '<?php echo DB_NAME; ?>' existe</li>
                            </ul>
                            <p class="mt-3">Détails techniques : <?php echo htmlspecialchars($debug_info[0] ?? ''); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <a href="index.php" class="back-link">
                        <i class="fas fa-arrow-left"></i>
                        Retour à l'accueil
                    </a>
                    
                    <div class="login-card">
                        <div class="login-header">
                            <i class="fas fa-graduation-cap fa-3x mb-3"></i>
                            <h3 class="mb-0">Connexion</h3>
                            <p class="mb-0 opacity-75">Accédez à votre espace personnel</p>
                        </div>
                        
                        <div class="login-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?php echo htmlspecialchars($error); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($debug_info)): ?>
                                <div class="alert alert-info alert-dismissible fade show" role="alert">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>DEBUG INFO:</strong><br>
                                    <?php foreach ($debug_info as $info): ?>
                                        • <?php echo htmlspecialchars($info); ?><br>
                                    <?php endforeach; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" id="loginForm">
                                <div class="user-type-selector">
                                    <div class="user-type-btn" data-type="etudiant">
                                        <i class="fas fa-user-graduate mb-2"></i>
                                        <div>Étudiant</div>
                                    </div>
                                    <div class="user-type-btn" data-type="professeur">
                                        <i class="fas fa-chalkboard-teacher mb-2"></i>
                                        <div>Professeur</div>
                                    </div>
                                    <div class="user-type-btn" data-type="admin">
                                        <i class="fas fa-user-shield mb-2"></i>
                                        <div>Admin</div>
                                    </div>
                                    <div class="user-type-btn" data-type="universite">
                                        <i class="fas fa-university mb-2"></i>
                                        <div>Université</div>
                                    </div>
                                </div>
                                
                                <input type="hidden" name="user_type" id="userType" value="etudiant">
                                
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="username" name="username" placeholder="Nom d'utilisateur" required>
                                    <label for="username">
                                        <i class="fas fa-user me-2"></i>
                                        Nom d'utilisateur
                                    </label>
                                </div>
                                
                                <div class="form-floating">
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Mot de passe" required>
                                    <label for="password">
                                        <i class="fas fa-lock me-2"></i>
                                        Mot de passe
                                    </label>
                                </div>
                                
                                <button type="submit" class="btn btn-login">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    Se connecter
                                </button>
                            </form>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // User type selector
        const userTypeBtns = document.querySelectorAll('.user-type-btn');
        const userTypeInput = document.getElementById('userType');
        
        userTypeBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                // Remove active class from all buttons
                userTypeBtns.forEach(b => b.classList.remove('active'));
                
                // Add active class to clicked button
                this.classList.add('active');
                
                // Update hidden input
                userTypeInput.value = this.dataset.type;
                
                // Update form labels based on user type
                updateFormLabels(this.dataset.type);
            });
        });
        
        // Set default active state
        document.querySelector('[data-type="etudiant"]').classList.add('active');
        
        // Initialize form labels for default user type
        updateFormLabels('etudiant');
        
        function updateFormLabels(userType) {
            const usernameLabel = document.querySelector('label[for="username"]');
            const passwordLabel = document.querySelector('label[for="password"]');
            const usernameInput = document.getElementById('username');
            
            switch(userType) {
                case 'etudiant':
                    usernameLabel.innerHTML = '<i class="fas fa-envelope me-2"></i>Email étudiant';
                    usernameInput.placeholder = 'votre.email@universite.fr';
                    usernameInput.type = 'email';
                    break;
                case 'professeur':
                    usernameLabel.innerHTML = '<i class="fas fa-envelope me-2"></i>Email professeur';
                    usernameInput.placeholder = 'professeur@universite.fr';
                    usernameInput.type = 'email';
                    break;
                case 'admin':
                    usernameLabel.innerHTML = '<i class="fas fa-user me-2"></i>Nom d\'utilisateur';
                    usernameInput.placeholder = 'admin';
                    usernameInput.type = 'text';
                    break;
                case 'universite':
                    usernameLabel.innerHTML = '<i class="fas fa-envelope me-2"></i>Email université';
                    usernameInput.placeholder = 'contact@universite.fr';
                    usernameInput.type = 'email';
                    break;
            }
        }
        
        // Form validation
        const form = document.getElementById('loginForm');
        form.addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            const userType = userTypeInput.value;
            
            if (!username || !password || !userType) {
                e.preventDefault();
                showToast('Veuillez remplir tous les champs', 'warning');
                return false;
            }
        });
        
        // Toast notification function
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toast-container') || createToastContainer();
            
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            toast.addEventListener('hidden.bs.toast', function() {
                toast.remove();
            });
        }
        
        function createToastContainer() {
            const container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
            return container;
        }
    </script>
</body>
</html> 