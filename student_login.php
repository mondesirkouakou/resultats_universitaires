<?php
require_once 'config.php';
require_once 'includes/user_accounts.php';

$error = '';
$message = '';

// Si déjà connecté, rediriger vers le dashboard approprié
if (isset($_SESSION['user_type']) && ($_SESSION['user_type'] === 'etudiant' || $_SESSION['user_type'] === 'professeur')) {
    redirect($_SESSION['user_type'] === 'etudiant' ? 'student_dashboard.php' : 'professor_dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Debug complet
    error_log("DEBUG LOGIN - Tentative de connexion pour: " . $email);
    
    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs';
        error_log("DEBUG LOGIN - Champs vides");
    } else {
        try {
            $pdo = getDatabaseConnection();
            error_log("DEBUG LOGIN - Connexion DB OK");
            
            $result = authenticateUser($pdo, $email, $password);
            error_log("DEBUG LOGIN - Résultat authenticateUser: " . json_encode($result));
            
            if ($result['success']) {
                // Connexion réussie
                $_SESSION['user_id'] = $result['user_data']['id'];
                $_SESSION['user_type'] = $result['user_data']['type'];
                $_SESSION['user_nom'] = $result['user_data']['nom'];
                $_SESSION['user_prenom'] = $result['user_data']['prenom'];
                $_SESSION['user_email'] = $result['user_data']['email'];
                $_SESSION['premiere_connexion'] = $result['user_data']['premiere_connexion'];
                
                error_log("DEBUG LOGIN - Session créée: " . json_encode($_SESSION));
                
                // Forcer la sauvegarde de la session
                session_write_close();
                session_start();
                
                // Si première connexion, rediriger vers changement de mot de passe
                if ($result['user_data']['premiere_connexion']) {
                    error_log("DEBUG LOGIN - Redirection vers change_password.php");
                    header("Location: change_password.php");
                    exit();
                } else {
                    // Rediriger vers le dashboard approprié
                    $dashboard = $result['user_data']['type'] === 'etudiant' ? 'student_dashboard.php' : 'professor_dashboard.php';
                    error_log("DEBUG LOGIN - Redirection vers: " . $dashboard);
                    header("Location: $dashboard");
                    exit();
                }
            } else {
                $error = $result['message'];
                error_log("DEBUG LOGIN - Erreur d'authentification: " . $result['message']);
            }
        } catch (Exception $e) {
            $error = 'Erreur système: ' . $e->getMessage();
            error_log("DEBUG LOGIN - Exception: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Étudiants/Professeurs - Système Universitaire</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }
        .login-header {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        .form-control:focus {
            border-color: #4CAF50;
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            border: none;
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #45a049, #4CAF50);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .admin-link {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            text-decoration: none;
            background: rgba(255, 255, 255, 0.2);
            padding: 10px 20px;
            border-radius: 50px;
            backdrop-filter: blur(10px);
        }
        .admin-link:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }
    </style>
</head>
<body>
    <a href="login.php" class="admin-link">
        <i class="fas fa-cog me-2"></i>Administration
    </a>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="login-container">
                    <div class="login-header">
                        <h2><i class="fas fa-graduation-cap me-3"></i>Espace Étudiants & Professeurs</h2>
                        <p class="mb-0">Connectez-vous avec vos identifiants</p>
                    </div>
                    
                    <div class="p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope me-2"></i>Adresse email
                                </label>
                                <input type="email" class="form-control form-control-lg" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                <div class="invalid-feedback">
                                    Veuillez saisir une adresse email valide.
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Mot de passe
                                </label>
                                <input type="password" class="form-control form-control-lg" id="password" name="password" required>
                                <div class="invalid-feedback">
                                    Veuillez saisir votre mot de passe.
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg btn-login">
                                    <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Première connexion ? Utilisez le mot de passe temporaire fourni par votre université.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Bootstrap form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
